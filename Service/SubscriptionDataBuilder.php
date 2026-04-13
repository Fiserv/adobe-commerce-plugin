<?php
declare(strict_types=1);
namespace Fiserv\Payments\Service;

use Fiserv\Payments\Model\Subscription\OrderFactory as SubscriptionOrderFactory;
use Fiserv\Payments\Model\ResourceModel\Subscription\Order\CollectionFactory as SubscriptionCollectionFactory;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

/**
 * Shared data-building service for recurring subscription AJAX endpoints.
 *
 * Both the frontend (RecurringData) and admin (Adminhtml/RecurringData) controllers
 * delegate all row-building logic here. The controllers themselves are thin: they
 * only handle auth, scope filtering (customer vs all), and JSON response wiring.
 */
class SubscriptionDataBuilder
{
    /** @var array<int, list<array{public_hash:string,label:string}>> */
    private array $vaultTokenCache = [];

    public function __construct(
        private readonly SubscriptionOrderFactory $subscriptionOrderFactory,
        private readonly SubscriptionCollectionFactory $subscriptionCollectionFactory,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly PriceHelper $priceHelper,
        private readonly ResourceConnection $resourceConnection,
        private readonly CommerceHubConfig $commerceHubConfig
    ) {}

    /**
     * Build the rows array for a given subscription collection filter.
     *
     * @param array|null $customerFilter  ['field' => ..., 'condition' => ...]
     * or null to load all rows (admin).
     * @param bool $includeCustomerInfo Whether to embed customer_name / email / id (admin only).
     * @param bool $includeOrderMeta Whether to embed date / amount / order_entity_id (frontend only).
     * @return array{success: bool, rows?: array, error?: string}
     */
    public function build(
        ?array $customerFilter = null,
        bool $includeCustomerInfo = false,
        bool $includeOrderMeta = false
    ): array {
        try {
            $collection = $this->subscriptionOrderFactory->create()->getCollection();
            // Optionally scope to a single customer
            if ($customerFilter !== null) {
                foreach ($customerFilter as $filter) {
                    $collection->addFieldToFilter($filter['field'], $filter['condition']);
                }
            }

            $collection->setOrder('created_at', 'DESC');

            // ---- Gather chain keys and increment IDs in a single pass ----
            $incrementIdMap = [];
            $chainKeyMap = [];
            foreach ($collection as $subscription) {
                $orderIncrementId = (string)$subscription->getOrderIncrementId();
                if ($orderIncrementId !== '') {
                    $incrementIdMap[$orderIncrementId] = true;
                }
                $chainKey = (string)($subscription->getOriginalOrderIncrement() ?: $subscription->getOrderIncrementId() ?: '');
                if ($chainKey !== '') {
                    $chainKeyMap[$chainKey] = true;
                }
            }

            // ---- Head and latest maps (two small queries) ----
            $headStatusMap = [];
            $headIdMap = [];
            $headIsActiveMap = [];
            $headPendingCardMap = [];
            $latestIdMap = [];
            $chainKeysList = array_keys($chainKeyMap);

            if (!empty($chainKeysList)) {
                $firstRows = $this->subscriptionCollectionFactory->create();
                $firstRows->addFieldToFilter('original_order_increment', ['in' => $chainKeysList]);
                $firstRows->addFieldToFilter('sequence', 'FIRST');
                foreach ($firstRows as $headSubscription) {
                    $headChainKey = (string)$headSubscription->getOriginalOrderIncrement();
                    if ($headChainKey !== '') {
                        $headStatusMap[$headChainKey] = (string)$headSubscription->getStatus();
                        $headIdMap[$headChainKey] = (int)$headSubscription->getId();
                        $headIsActiveMap[$headChainKey] = (int)$headSubscription->getData('is_active');
                        $headPendingCardMap[$headChainKey] = (string)($headSubscription->getData('change_payment_card') ?? '');
                    }
                }

                $latestRows = $this->subscriptionCollectionFactory->create();
                $latestRows->addFieldToFilter('original_order_increment', ['in' => $chainKeysList]);
                $latestRows->setOrder('created_at', 'DESC');
                foreach ($latestRows as $latestSubscription) {
                    $latestChainKey = (string)$latestSubscription->getOriginalOrderIncrement();
                    if ($latestChainKey !== '' && !isset($latestIdMap[$latestChainKey])) {
                        $latestIdMap[$latestChainKey] = (int)$latestSubscription->getEntityId();
                    }
                }
            }

            // ---- Load matching sales orders ----
            $orderMap = [];
            $incrementIdList = array_keys($incrementIdMap);
            if (!empty($incrementIdList)) {
                $orderCollection = $this->orderCollectionFactory->create()
                    ->addFieldToFilter('increment_id', ['in' => $incrementIdList]);
                if ($includeOrderMeta) {
                    $orderCollection->addFieldToSelect(
                        ['entity_id', 'increment_id', 'created_at', 'grand_total', 'order_currency_code']
                    );
                }
                foreach ($orderCollection as $salesOrder) {
                    $orderMap[(string)$salesOrder->getIncrementId()] = $salesOrder;
                }
            }

            // ---- Build rows ----
            $rows = [];
            foreach ($collection as $subscription) {
                $orderIncrementId = (string)$subscription->getOrderIncrementId();
                $chainKey = (string)($subscription->getOriginalOrderIncrement() ?: $subscription->getOrderIncrementId() ?: '');
                $normalizedChainKey = preg_replace('/-\d+$/', '', $chainKey) ?: $chainKey;

                $isChild = ($orderIncrementId !== '' && (bool)preg_match('/-\d+$/', $orderIncrementId));
                $isParent = !$isChild;

                $order = $orderMap[$orderIncrementId] ?? null;
                $subscriptionId = (int)$subscription->getEntityId();

                // Interval label
                $intervalValue = (string)($subscription->getData('interval_value') ?: '');
                $intervalUnit = (string)($subscription->getData('interval_unit') ?: '');
                $interval = trim($intervalValue . ' ' . $intervalUnit);

                // Status
                $status = strtolower((string)($subscription->getStatus() ?? $subscription->getData('status') ?? ''));
                $nextBilling = (string)($subscription->getData('next_billing_datetime') ?: '');

                $latestSubscriptionId = $latestIdMap[$normalizedChainKey] ?? 0;
                $isLatest = ($latestSubscriptionId > 0 && $subscriptionId === $latestSubscriptionId);

                // chainCancelled is true ONLY when the user has explicitly ended the
                // subscription (is_active = 0 on the head row).
                // It must NOT be based on the head row's 'status' column, because that
                // column briefly becomes 'processing' during each cron cycle, which would
                // incorrectly show "Subscription canceled" on every row mid-cycle.
                $chainCancelled = isset($headIsActiveMap[$normalizedChainKey])
                    && $headIsActiveMap[$normalizedChainKey] === 0;

                // Display status
                // Rules:
                //   - 'cancelled' always shows as-is (void/end-subscription result)
                //   - If the entire chain has been ended (chainCancelled), show raw status
                //   - The single latest row in the chain → 'active'
                //   - All other rows (including the parent once children exist) → 'processed'
                //   - Raw 'processing' (brief mid-cycle DB state) is normalized to 'processed'
                //     so the user never sees a transient internal state
                $displayStatus = $status;
                if (!$chainCancelled && $status !== 'cancelled') {
                    $displayStatus = $isLatest ? 'active' : 'processed';
                }

                // Next billing for parent rows: created_at + interval (stable)
                $displayNext = $nextBilling;
                if ($isParent) {
                    $computed = $this->computeFirstNextBilling($subscription);
                    if ($computed !== '') {
                        $displayNext = $computed;
                    }
                }

                // Card last 4
                $rawToken = '';
                if ($order && $order->getPayment()) {
                    $additionalInfo = (array)$order->getPayment()->getAdditionalInformation();
                    $rawToken = (string)($additionalInfo['payment_token'] ?? '');
                }
                if ($rawToken === '') {
                    try {
                        $rawToken = (string)($subscription->getPaymentToken() ?? '');
                    } catch (\Throwable $e) {}
                }
                $last4 = '';
                if ($rawToken !== '') {
                    $tokenPart = explode('$$TS$$=', $rawToken, 2)[0];
                    $digits = preg_replace('/\D+/', '', $tokenPart);
                    $last4 = $digits !== '' ? substr($digits, -4) : '';
                }
                $rootSubscriptionId = $headIdMap[$normalizedChainKey] ?? $subscriptionId;

                // change_payment_card: only meaningful on the chain head; propagate to all rows in the chain
                // so the JS can show "Updated. Future renewals will use..." on the relevant row.
                // Clear it when the chain has been cancelled — the notice must never appear on a
                // terminated subscription.
                $pendingCardLabel = (!$chainCancelled)
                    ? ($headPendingCardMap[$normalizedChainKey] ?? '')
                    : '';

                $row = [
                    'subscription_id' => $subscriptionId,
                    'increment_id' => $orderIncrementId,
                    'interval' => $interval !== '' ? $interval : 'N/A',
                    'status' => $displayStatus,
                    'card_last4' => $last4,
                    'next_billing' => $displayNext !== '' ? $displayNext . ' UTC' : 'N/A',
                    'chain_cancelled' => $chainCancelled,
                    'is_latest' => $isLatest,
                    'is_parent' => $isParent,
                    'root_sub_id' => $rootSubscriptionId,
                    'change_payment_card' => $pendingCardLabel,
                ];

                // Admin-only fields
                if ($includeCustomerInfo) {
                    $customerName = (string)($subscription->getData('customer_name') ?: '');
                    $customerEmail = (string)($subscription->getData('customer_email') ?: '');
                    $customerId = (int)($subscription->getData('customer_id') ?: 0);

                    if (($customerName === '' || $customerEmail === '') && $order) {
                        $customerName = $customerName ?: (string)$order->getCustomerName();
                        $customerEmail = $customerEmail ?: (string)$order->getCustomerEmail();
                        $customerId = $customerId ?: (int)$order->getCustomerId();
                    }

                    // Include vault tokens for the latest active row so the JS can
                    // populate the "Update card" dropdown without a separate AJAX call.
                    // For non-latest rows we pass an empty array to keep the payload small.
                    $vaultTokens = ($isLatest && $displayStatus === 'active' && $customerId > 0)
                        ? $this->resolveVaultTokensForCustomer($customerId)
                        : [];

                    $row += [
                        'customer_name' => $customerName ?: 'Guest',
                        'customer_email' => $customerEmail ?: 'N/A',
                        'customer_id' => $customerId,
                        'amount' => $order
                            ? (string)$this->priceHelper->currency((float)$order->getGrandTotal(), true, false)
                            : 'N/A',
                        'vault_tokens' => $vaultTokens,
                    ];
                }

                // Frontend-only fields
                if ($includeOrderMeta) {
                    $date = $order ? (string)$order->getCreatedAt() : (string)$subscription->getData('created_at');
                    $amount = $order ? (float)$order->getGrandTotal() : null;
                    $row += [
                        'date' => $date,
                        'amount' => $amount !== null
                            ? $this->priceHelper->currency($amount, true, false)
                            : 'N/A',
                        'order_entity_id' => $order ? (int)$order->getEntityId() : 0,
                    ];
                }

                $rows[] = $row;
            }

            return ['success' => true, 'rows' => $rows];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Computes created_at + interval for a parent row.
     * Returns the stable "first billing due" time shown in the Next Billing column.
     */
    private function computeFirstNextBilling($subscription): string
    {
        try {
            $createdAt = (string)($subscription->getData('created_at') ?: $subscription->getCreatedAt() ?: '');
            $intervalValue = (int)($subscription->getData('interval_value') ?: 1);
            $intervalUnit = strtolower((string)($subscription->getData('interval_unit') ?: 'minute'));

            if ($createdAt === '') {
                return '';
            }

            $allowed = ['minute', 'day', 'week', 'month', 'year'];
            if (!in_array($intervalUnit, $allowed, true)) {
                $intervalUnit = 'minute';
            }

            $dt = new \DateTime($createdAt, new \DateTimeZone('UTC'));
            $dt->modify("+{$intervalValue} {$intervalUnit}");
            $dt->setTime((int)$dt->format('H'), (int)$dt->format('i'), 0);

            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Resolves active vault tokens for a customer, filtered by the current merchant.
     * Results are cached per customer_id to avoid duplicate DB hits within one request.
     *
     * @param int $customerId
     * @return list<array{public_hash:string,label:string}>
     */
    private function resolveVaultTokensForCustomer(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }

        if (isset($this->vaultTokenCache[$customerId])) {
            return $this->vaultTokenCache[$customerId];
        }

        $tokens = [];

        try {
            $connection = $this->resourceConnection->getConnection();
            $vaultTable = $this->resourceConnection->getTableName('vault_payment_token');
            $currentMerchant = (string)($this->commerceHubConfig->getMerchantId() ?? '');

            $select = $connection->select()
                ->from($vaultTable, ['public_hash', 'details', 'expires_at'])
                ->where('customer_id = ?', $customerId)
                ->where('payment_method_code = ?', 'fiserv_commercehub')
                ->where('is_active = 1')
                ->where('is_visible = 1')
                ->where('(expires_at IS NULL OR expires_at != ?)', '0000-00-00 00:00:00')
                ->order('entity_id DESC');

            foreach ($connection->fetchAll($select) as $row) {
                $publicHash = (string)($row['public_hash'] ?? '');
                if ($publicHash === '') {
                    continue;
                }

                $details = $row['details'] !== '' ? (array)json_decode((string)$row['details'], true) : [];
                $tokenMerchantId = (string)($details['merchantId'] ?? '');

                // Skip tokens belonging to a different merchant
                if ($currentMerchant !== '' && $tokenMerchantId !== '' && $tokenMerchantId !== $currentMerchant) {
                    continue;
                }

                $cardType = (string)($details['type'] ?? $details['ccType'] ?? '');
                $maskedCC = (string)($details['maskedCC'] ?? $details['masked_cc'] ?? '');
                $expiry = (string)($details['expirationDate'] ?? $details['expiration_date'] ?? '');

                if ($cardType === '' || $maskedCC === '' || $expiry === '') {
                    continue;
                }

                $label = trim(preg_replace('/\s+/', ' ', sprintf('%s %s %s', $cardType, $maskedCC, $expiry)));
                $tokens[] = [
                    'public_hash' => $publicHash,
                    'label' => $label !== '' ? $label : $publicHash,
                ];
            }
        } catch (\Throwable $e) {
            $tokens = [];
        }

        $this->vaultTokenCache[$customerId] = $tokens;
        return $tokens;
    }
}