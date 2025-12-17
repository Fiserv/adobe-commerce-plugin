<?php
declare(strict_types=1);
namespace Fiserv\Payments\Block\Subscription;

use Fiserv\Payments\Model\ResourceModel\Subscription\Order\CollectionFactory as SubscriptionCollectionFactory;
use Fiserv\Payments\Model\Subscription\OrderFactory as SubscriptionOrderFactory;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Fiserv\Payments\Model\System\Utils\PaymentTokenUtil;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Vault\Block\Customer\CreditCards as VaultCreditCardsBlock;

class Recurring extends Template
{
    /** @var array<string,string>|null chainKey => head status */
    private ?array $headStatusMap = null;
    /** @var array<string,string>|null chainKey => head payment_token */
    private ?array $headTokenMap = null;
    /** @var array<string,int>|null chainKey => head subscription entity_id */
    private ?array $headIdMap = null;
    /** @var array<string,\Fiserv\Payments\Model\Subscription\Order>|null chainKey => head subscription model */
    private ?array $headSubMap = null;
    /** @var array<string,int>|null chainKey => latest subscription entity_id */
    private ?array $latestIdMap = null;
    /** @var OrderInterface|\Magento\Sales\Model\Order|false|null */
    private $viewedOrder = null;

    public function __construct(
        Context $context,
        private readonly SubscriptionOrderFactory $subscriptionOrderFactory,
        private readonly SubscriptionCollectionFactory $subscriptionCollectionFactory,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly CustomerSession $customerSession,
        private readonly PriceHelper $priceHelper,
        private readonly CommerceHubConfig $commerceHubConfig,
        private readonly RequestInterface $request,
        private readonly OrderRepositoryInterface $orderRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

	/**
	 * @return array<int, array{subscription:\Fiserv\Payments\Model\Subscription\Order, order:\Magento\Sales\Model\Order|null}>
	 */
	public function getPriorRecurringPayments(): array
	{
		$subscriptionCollection = $this->getScopedSubscriptionCollection();
		if ($subscriptionCollection === null) {
			return [];
		}

        $subscriptionCollection->setOrder('created_at', 'DESC');

        $allIncrementIds = [];
        $allChainKeys = [];

        foreach ($subscriptionCollection as $row) {
            $incrementId = (string)$row->getOrderIncrementId();
            if ($incrementId !== '') {
                $allIncrementIds[$incrementId] = true;
            }

            $chainKey = $this->resolveChainKey($row);
            if ($chainKey !== '') {
                $allChainKeys[$chainKey] = true;
            }
        }

        $chainKeysList = array_keys($allChainKeys);
        $this->buildHeadMaps($chainKeysList);
        $this->latestIdMap = $this->buildLatestIdMap($chainKeysList);

        $ordersByIncrementId = $this->loadSalesOrders(array_keys($allIncrementIds));

        $result = [];
        foreach ($subscriptionCollection as $row) {
            $result[] = [
                'subscription' => $row,
                'order' => $ordersByIncrementId[(string)$row->getOrderIncrementId()] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Returns a single-row data array for the order currently being viewed on
     * sales_order_view. Returns null if the order is not part of a subscription.
     */
    public function getSingleOrderViewData(): ?array
    {
        $order = $this->getViewedOrder();
        if ($order === false) {
            return null;
        }

        $incrementId = (string)$order->getIncrementId();

        $subRow = $this->subscriptionOrderFactory->create()->getCollection()
            ->addFieldToFilter('order_increment_id', $incrementId)
            ->setPageSize(1)
            ->getFirstItem();

        if (!$subRow || !$subRow->getEntityId()) {
            return null;
        }

        $grandTotal = (float)$order->getGrandTotal();
        $currencyCode = (string)$order->getOrderCurrencyCode();
        $formattedAmount = '$' . number_format($grandTotal, 2) . ' ' . $currencyCode;

        $transactionId = $this->extractTransactionId($order);

        $rawToken = '';
        $payment = $order->getPayment();
        if ($payment) {
            $rawToken = (string)(((array)$payment->getAdditionalInformation())['payment_token'] ?? '');
        }
        if ($rawToken === '') {
            $rawToken = (string)($subRow->getPaymentToken() ?? '');
        }

        return [
            'increment_id' => $incrementId,
            'amount' => $formattedAmount,
            'transaction_id' => $transactionId,
            'card_label' => $this->buildCardLabel($rawToken),
            'date' => (string)$order->getCreatedAt(),
            'status' => (string)($subRow->getStatus() ?? ''),
        ];
    }

    /**
     * Returns the payment_token stored on the chain-head (FIRST) row.
     * Used by the phtml to identify the currently-active card for a chain.
     */
    public function getHeadPublicHashForSubscription($subscription): string
    {
        $chainKey = $this->resolveChainKey($subscription);
        if ($chainKey === '') {
            return '';
        }

        if ($this->headTokenMap !== null && array_key_exists($chainKey, $this->headTokenMap)) {
            return (string)$this->headTokenMap[$chainKey];
        }

        try {
            return (string)$subscription->getPaymentToken();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Returns the entity_id of the chain-head (FIRST) subscription row.
     * Used by the UI to target the full-chain cancel action.
     */
    public function getHeadSubscriptionIdForSubscription($subscription): int
    {
        $chainKey = $this->normalizeChainKey($this->resolveChainKey($subscription));

        if ($this->headIdMap !== null && array_key_exists($chainKey, $this->headIdMap)) {
            return (int)$this->headIdMap[$chainKey];
        }

        return 0;
    }

    /**
     * Returns true when the subscription chain has been ended (head is no longer 'active').
     * Drives the "Subscription canceled" label in the phtml.
     */
    public function getChainCancelledForSubscription($subscription): bool
    {
        $chainKey = $this->normalizeChainKey($this->resolveChainKey($subscription));
        if ($chainKey === '') {
            return false;
        }

        if ($this->headStatusMap !== null && array_key_exists($chainKey, $this->headStatusMap)) {
            return strtolower((string)$this->headStatusMap[$chainKey]) !== 'active';
        }
        return false;
    }

    /**
     * Returns the entity_id of the most recently created row in this chain.
     * The phtml marks that row as "Active" and shows the Update-card dropdown on it.
     */
    public function getLatestSubscriptionIdByChain($subscription): int
    {
        $chainKey = $this->normalizeChainKey($this->resolveChainKey($subscription));
        if ($chainKey === '') {
            return 0;
        }

        if ($this->latestIdMap !== null && array_key_exists($chainKey, $this->latestIdMap)) {
            return (int)$this->latestIdMap[$chainKey];
        }
        return 0;
    }

    /**
     * Returns the chain-head (FIRST) subscription model row for the given subscription.
     * Used by the phtml to read pending_card_label for the "Updated" feedback message.
     *
     * @param  \Fiserv\Payments\Model\Subscription\Order $subscription
     * @return \Fiserv\Payments\Model\Subscription\Order|null
     */
    public function getHeadSubscriptionForSubscription($subscription): ?\Fiserv\Payments\Model\Subscription\Order
    {
        $chainKey = $this->normalizeChainKey($this->resolveChainKey($subscription));
        if ($chainKey === '') {
            return null;
        }

        if ($this->headSubMap !== null && array_key_exists($chainKey, $this->headSubMap)) {
            return $this->headSubMap[$chainKey];
        }
        return null;
    }

    /**
     * Computes the first scheduled next-billing time for the parent (FIRST) row:
     *   created_at + interval, truncated to the minute.
     * This value is stable and never drifts with each renewal cycle.
     */
    public function getFirstNextBillingForHead($subscription): string
    {
        try {
            $createdAt = (string)($subscription->getCreatedAt() ?? '');
            $intervalValue = (int)($subscription->getIntervalValue() ?: 1);
            $intervalUnit = strtolower((string)($subscription->getIntervalUnit() ?: 'minute'));

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
        } catch (\Throwable) {
            return '';
        }
    }

    /** Returns vault tokens visible to the current logged-in customer, filtered to the active merchant account.*/
    public function getVaultTokens(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return [];
        }

        /** @var VaultCreditCardsBlock|null $vaultBlock */
        $vaultBlock = $this->getLayout()->createBlock(VaultCreditCardsBlock::class);
        if (!$vaultBlock) {
            return [];
        }

        $currentMerchantId = (string)($this->commerceHubConfig->getMerchantId() ?? '');
        $tokens = [];

        foreach ($vaultBlock->getPaymentTokens() as $vaultToken) {
            if (!$vaultToken || !$vaultToken->getPublicHash()) {
                continue;
            }

            $details = (array)json_decode((string)($vaultToken->getTokenDetails() ?? ''), true);
            $tokenMerchantId = (string)($details['merchantId'] ?? '');

            // Skip tokens belonging to a different merchant account.
            if ($currentMerchantId !== '' && $tokenMerchantId !== '' && $tokenMerchantId !== $currentMerchantId) {
                continue;
            }

            $cardType = (string)($details['type'] ?? $details['ccType'] ?? 'Card');
            $masked = (string)($details['maskedCC'] ?? $details['masked_cc'] ?? '');
            $expiry = (string)($details['expirationDate'] ?? $details['expiration_date'] ?? '');
            $label = preg_replace('/\s+/', ' ', trim("$cardType $masked $expiry")) ?: '';

            $tokens[] = [
                'public_hash' => (string)$vaultToken->getPublicHash(),
                'label' => $label !== '' ? $label : (string)$vaultToken->getPublicHash(),
            ];
        }
        return $tokens;
    }

    /** Formats a float amount using the store's currency display. */
    public function formatPrice(float $amount): string
    {
        return (string)$this->priceHelper->currency($amount, true, false);
    }

    /**
     * Returns the Magento order for the current sales_order_view page,
     * loading it only once per request. Returns false if unavailable.
     *
     * @return OrderInterface|\Magento\Sales\Model\Order|false
     */
    private function getViewedOrder()
    {
        if ($this->viewedOrder !== null) {
            return $this->viewedOrder;
        }

        $orderId = (int)$this->request->getParam('order_id');
        if ($orderId <= 0) {
            return $this->viewedOrder = false;
        }

        try {
            return $this->viewedOrder = $this->orderRepository->get($orderId);
        } catch (\Throwable $e) {
            return $this->viewedOrder = false;
        }
    }

    /**
     * Returns the subscription collection scoped to the current context:
     * - On sales_order_view: rows for that order's chain (up to and including the viewed order).
     * - On account pages: all chains for the logged-in customer.
     */
    private function getScopedSubscriptionCollection(): ?\Magento\Framework\Data\Collection\AbstractDb
    {
        $viewedOrder = $this->getViewedOrder();
        if ($viewedOrder !== false) {
            $incrementId = (string)$viewedOrder->getIncrementId();
            $chainKey = $this->resolveChainKeyForOrderIncrement($incrementId);
            if ($chainKey === '') {
                return null;
            }

            $collection = $this->subscriptionOrderFactory->create()->getCollection();
            $collection->addFieldToFilter(
                ['original_order_increment', 'order_increment_id'],
                [['eq' => $chainKey], ['eq' => $chainKey]]
            );
            // Exclude chain rows created after the currently-viewed order.
            $createdAt = (string)$viewedOrder->getCreatedAt();
            if ($createdAt !== '') {
                $collection->addFieldToFilter('created_at', ['lteq' => $createdAt]);
            }

            return $collection;
        }

        // Account page: scope to the logged-in customer.
        $customerId = (int)$this->customerSession->getCustomerId();
        $customerEmail = '';
        try {
            $customer = $this->customerSession->getCustomer();
            $customerEmail = $customer ? (string)$customer->getEmail() : '';
        } catch (\Throwable $e) {}

        if ($customerId <= 0 && $customerEmail === '') {
            return null;
        }

        $collection = $this->subscriptionOrderFactory->create()->getCollection();
        if ($customerId > 0 && $customerEmail !== '') {
            $collection->addFieldToFilter(
                ['customer_id', 'customer_email'],
                [['eq' => $customerId], ['eq' => $customerEmail]]
            );
        } elseif ($customerId > 0) {
            $collection->addFieldToFilter('customer_id', $customerId);
        } else {
            $collection->addFieldToFilter('customer_email', $customerEmail);
        }
        return $collection;
    }

    /**
     * Resolves the canonical chain key (original_order_increment of the FIRST row)
     * for a given order increment ID. Falls back to stripping the "-n" suffix if
     * no subscription row is found.
     */
    private function resolveChainKeyForOrderIncrement(string $orderIncrementId): string
    {
        $orderIncrementId = trim($orderIncrementId);
        if ($orderIncrementId === '') {
            return '';
        }

        $row = $this->subscriptionOrderFactory->create()->getCollection()
            ->addFieldToFilter('order_increment_id', $orderIncrementId)
            ->setPageSize(1)
            ->getFirstItem();

        if ($row && $row->getEntityId()) {
            return (string)($row->getOriginalOrderIncrement() ?: $row->getOrderIncrementId() ?: '');
        }

        // Fallback: strip "-n" suffix to derive the root order ID and look that up.
        $rootId = preg_replace('/-\d+$/', '', $orderIncrementId) ?: $orderIncrementId;
        $rootRow = $this->subscriptionOrderFactory->create()->getCollection()
            ->addFieldToFilter('order_increment_id', $rootId)
            ->setPageSize(1)
            ->getFirstItem();

        if ($rootRow && $rootRow->getEntityId()) {
            return (string)($rootRow->getOriginalOrderIncrement() ?: $rootRow->getOrderIncrementId() ?: '');
        }
        return $rootId;
    }

    /**
     * Extracts the canonical chain key from a subscription model row.
     * Chain key = original_order_increment, or order_increment_id if that is empty.
     */
    private function resolveChainKey($subscriptionRow): string
    {
        try {
            return (string)($subscriptionRow->getOriginalOrderIncrement() ?: $subscriptionRow->getOrderIncrementId() ?: '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Strips any "-n" suffix from a chain key so map lookups always match
     * the root order increment ID (e.g. "000007826-1" → "000007826").
     */
    private function normalizeChainKey(string $chainKey): string
    {
        return $chainKey !== '' ? (preg_replace('/-\d+$/', '', $chainKey) ?: $chainKey) : '';
    }

    /**
     * Populates headTokenMap, headIdMap, and headStatusMap in a single pass
     * over the FIRST-sequence rows for the given chain keys.
     *
     * buildHeadStatusMap adds a fallback query for chains whose head row does
     * not have original_order_increment set.
     */
    private function buildHeadMaps(array $chainKeys): void
    {
        $this->headTokenMap = [];
        $this->headIdMap = [];
        $this->headStatusMap = [];
        $this->headSubMap = [];

        if (empty($chainKeys)) {
            return;
        }

        $firstRowCollection = $this->subscriptionCollectionFactory->create();
        $firstRowCollection->addFieldToFilter('original_order_increment', ['in' => $chainKeys]);
        $firstRowCollection->addFieldToFilter('sequence', 'FIRST');

        foreach ($firstRowCollection as $row) {
            $chainKey = (string)$row->getOriginalOrderIncrement();
            if ($chainKey === '') {
                continue;
            }
            $this->headTokenMap[$chainKey] = (string)$row->getPaymentToken();
            $this->headIdMap[$chainKey] = (int)$row->getId();
            $this->headStatusMap[$chainKey] = (string)$row->getStatus();
            $this->headSubMap[$chainKey] = $row;
        }

        // Fallback for head rows that have no original_order_increment stored.
        $missingKeys = array_diff($chainKeys, array_keys($this->headStatusMap));
        if (!empty($missingKeys)) {
            $fallback = $this->subscriptionCollectionFactory->create();
            $fallback->addFieldToFilter('order_increment_id', ['in' => array_values($missingKeys)]);

            foreach ($fallback as $row) {
                $key = preg_replace('/-\d+$/', '', (string)$row->getOrderIncrementId()) ?: '';
                if ($key !== '' && !array_key_exists($key, $this->headStatusMap)) {
                    $this->headTokenMap[$key] = (string)$row->getPaymentToken();
                    $this->headIdMap[$key] = (int)$row->getId();
                    $this->headStatusMap[$key] = (string)$row->getStatus();
                    $this->headSubMap[$key] = $row;
                }
            }
        }
    }

    /**
     * Builds a map of chainKey => entity_id of the most-recently created row.
     *
     * @param  string[] $chainKeys
     * @return array<string,int>
     */
    private function buildLatestIdMap(array $chainKeys): array
    {
        if (empty($chainKeys)) {
            return [];
        }

        $latestIdMap = [];

        $collection = $this->subscriptionCollectionFactory->create();
        $collection->addFieldToFilter('original_order_increment', ['in' => $chainKeys]);
        $collection->setOrder('created_at', 'DESC');

        foreach ($collection as $row) {
            $chainKey = (string)$row->getOriginalOrderIncrement();
            if ($chainKey === '' || array_key_exists($chainKey, $latestIdMap)) {
                continue;
            }
            $latestIdMap[$chainKey] = (int)$row->getEntityId();
        }

        return $latestIdMap;
    }

    /**
     * Loads sales_order rows by increment ID and returns them keyed by increment ID.
     *
     * @param  string[] $incrementIds
     * @return array<string, \Magento\Sales\Model\Order>
     */
    private function loadSalesOrders(array $incrementIds): array
    {
        if (empty($incrementIds)) {
            return [];
        }

        $map = [];
        $collection = $this->orderCollectionFactory->create()
            ->addFieldToFilter('increment_id', ['in' => $incrementIds])
            ->addFieldToSelect(['entity_id', 'increment_id', 'created_at', 'grand_total', 'status', 'state'])
            ->setOrder('created_at', 'DESC');

        foreach ($collection as $order) {
            $map[(string)$order->getIncrementId()] = $order;
        }
        return $map;
    }

    /** Extracts the CommerceHub transaction ID from an order's status history comments.*/
    private function extractTransactionId(OrderInterface $order): string
    {
        $historyItems = $order->getStatusHistories();
        if (!$historyItems) {
            return '';
        }

        $items = is_array($historyItems) ? $historyItems : iterator_to_array($historyItems);
        foreach (array_reverse($items) as $historyItem) {
            $comment = trim((string)$historyItem->getComment());
            if ($comment !== '' && str_contains($comment, 'Transaction ID:')
                && preg_match('/Transaction ID:\s*"?([a-f0-9]+)"?/i', $comment, $matches)) {
                return $matches[1];
            }
        }
        return '';
    }

    /**
     * Converts a raw payment token string into a masked card label (e.g. ************1111).
     * Delegates $$TS$$= suffix stripping to PaymentTokenUtil, then derives the last 4 digits.
     */
    private function buildCardLabel(string $rawToken): string
    {
        if ($rawToken === '') {
            return '';
        }
        $cleanToken = PaymentTokenUtil::getTokenDataFromPersistenceFormat($rawToken);
        $digits = preg_replace('/\D+/', '', $cleanToken);
        $last4 = $digits ? substr($digits, -4) : '';
        return $last4 !== '' ? '************' . $last4 : '';
    }
}