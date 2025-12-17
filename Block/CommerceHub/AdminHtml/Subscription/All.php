<?php
declare(strict_types=1);

namespace Fiserv\Payments\Block\CommerceHub\AdminHtml\Subscription;

use Fiserv\Payments\Model\Subscription\OrderFactory as SubscriptionOrderFactory;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory as SalesOrderFactory;

class All extends Template
{
	/** @var array<int, array<int, array{public_hash:string,label:string}>> */
	private array $vaultTokensCacheByCustomerId = [];

	public function __construct(
		Context $context,
		private readonly SubscriptionOrderFactory $subscriptionOrderFactory,
		private readonly CustomerRepositoryInterface $customerRepository,
		private readonly SalesOrderFactory $salesOrderFactory,
		private readonly ResourceConnection $resourceConnection,
		private readonly CommerceHubConfig $commerceHubConfig,
		private readonly PriceHelper $priceHelper,
		array $data = []
	) {
		parent::__construct($context, $data);
	}

	/**
	 * Format a float amount as a currency string (e.g. "$21.00").
	 */
	public function formatPrice(float $amount): string
	{
		return (string)$this->priceHelper->currency($amount, true, false);
	}

	public function getSubscriptions(): ?AbstractDb
	{
		try {
			$collection = $this->subscriptionOrderFactory->create()->getCollection();
			$collection->setOrder('created_at', 'DESC');
			return $collection;
		} catch (\Throwable) {
			return null;
		}
	}

	public function getHeadSubscriptionIdForSubscription($subscriptionRow): int
	{
		if (!$subscriptionRow) {
			return 0;
		}

		$selfId = (int)($subscriptionRow->getId() ?: 0);

		try {
			$chainKey = (string)($subscriptionRow->getData('original_order_increment')
				?: $subscriptionRow->getData('order_increment_id')
					?: $subscriptionRow->getData('order_increment')
						?: '');

			if ($chainKey === '') {
				return $selfId;
			}

			$chainKey = preg_replace('/-\d+$/', '', $chainKey) ?: $chainKey;
			if ($chainKey === '') {
				return $selfId;
			}

			$firstRowCollection = $this->subscriptionOrderFactory->create()->getCollection();
			$firstRowCollection->addFieldToFilter('original_order_increment', ['eq' => $chainKey]);
			$firstRowCollection->addFieldToFilter('sequence', ['eq' => 'FIRST']);
			$firstRowCollection->setPageSize(1);

			$firstRow = $firstRowCollection->getFirstItem();
			$firstRowId = (int)($firstRow ? $firstRow->getId() : 0);

			if ($firstRowId > 0) {
				return $firstRowId;
			}

			$fallbackCollection = $this->subscriptionOrderFactory->create()->getCollection();
			$fallbackCollection->addFieldToFilter(
				['original_order_increment', 'order_increment_id', 'order_increment'],
				[
					['eq' => $chainKey],
					['like' => $chainKey . '%'],
					['like' => $chainKey . '%'],
				]
			);
			$fallbackCollection->setOrder('created_at', 'ASC');
			$fallbackCollection->setPageSize(1);

			$oldestRow = $fallbackCollection->getFirstItem();
			$oldestRowId = (int)($oldestRow ? $oldestRow->getId() : 0);

			return $oldestRowId > 0 ? $oldestRowId : $selfId;
		} catch (\Throwable) {
			return $selfId;
		}
	}

	public function getOrderByIncrementId(string $incrementId): ?Order
	{
		$incrementId = trim(ltrim($incrementId, "# \t\n\r\0\x0B"));
		if ($incrementId === '') {
			return null;
		}

		try {
			$salesOrder = $this->salesOrderFactory->create()->loadByIncrementId($incrementId);
			return ($salesOrder && $salesOrder->getEntityId()) ? $salesOrder : null;
		} catch (\Throwable) {
			return null;
		}
	}

	/**
	 * Returns array with keys: type, name, email, customer_id
	 */
	public function getCustomerInfoByOrderIncrement(string $incrementId): array
	{
		if ($incrementId === '') {
			return ['type' => 'unknown', 'name' => '', 'email' => '', 'customer_id' => 0];
		}

		try {
			$salesOrder = $this->getOrderByIncrementId($incrementId);
			if (!$salesOrder) {
				return ['type' => 'unknown', 'name' => '', 'email' => '', 'customer_id' => 0];
			}

			$registeredCustomerId = (int)($salesOrder->getCustomerId() ?: 0);

			if ($registeredCustomerId > 0) {
				try {
					$customerAccount = $this->customerRepository->getById($registeredCustomerId);
					$customerFullName = trim($customerAccount->getFirstname() . ' ' . $customerAccount->getLastname());

					return [
						'type' => 'customer',
						'name' => $customerFullName,
						'email' => $customerAccount->getEmail(),
						'customer_id' => $registeredCustomerId
					];
				} catch (\Throwable) {
					// fall back to guest below
				}
			}

			return [
				'type' => 'guest',
				'name' => (string)$salesOrder->getCustomerName(),
				'email' => (string)$salesOrder->getCustomerEmail(),
				'customer_id' => 0
			];
		} catch (\Throwable) {
			return ['type' => 'unknown', 'name' => '', 'email' => '', 'customer_id' => 0];
		}
	}

	/**
	 *  Fetch vault tokens from vault_payment_token table using "vault-page-like" filters.
	 *
	 * Matches typical vault list behavior:
	 * - customer_id matches
	 * - payment_method_code matches
	 * - is_active=1
	 * - is_visible=1
	 *
	 * @return array<int, array{public_hash:string,label:string}>
	 */
	public function getVaultTokensByCustomerId(int $customerId): array
	{
		if ($customerId <= 0) {
			return [];
		}

		if (isset($this->vaultTokensCacheByCustomerId[$customerId])) {
			return $this->vaultTokensCacheByCustomerId[$customerId];
		}

		$vaultTokens = [];

		try {
			$connection = $this->resourceConnection->getConnection();
			$vaultTable = $this->resourceConnection->getTableName('vault_payment_token');
			$methodCode = 'fiserv_commercehub';

			// Only show tokens that belong to the current store's merchant account
			$currentMerchantId = (string)($this->commerceHubConfig->getMerchantId() ?? '');

			$select = $connection->select()
				->from($vaultTable, ['public_hash', 'details', 'expires_at'])
				->where('customer_id = ?', $customerId)
				->where('payment_method_code = ?', $methodCode)
				->where('is_active = 1')
				->where('is_visible = 1')
				// exclude zero-date rows
				->where('(expires_at IS NULL OR expires_at != ?)', '0000-00-00 00:00:00')
				->order('entity_id DESC');

			$vaultTokenRows = $connection->fetchAll($select);

			foreach ($vaultTokenRows as $tokenRow) {
				$publicHash = (string)($tokenRow['public_hash'] ?? '');
				if ($publicHash === '') continue;

				$detailsJson = (string)($tokenRow['details'] ?? '');
				$details = $detailsJson !== '' ? (array)json_decode($detailsJson, true) : [];

				// Skip tokens that belong to a different merchant account
				$merchantId = (string)($details['merchantId'] ?? '');
				if ($currentMerchantId !== '' && $merchantId !== '' && $merchantId !== $currentMerchantId) {
					continue;
				}

				$cardType = (string)($details['type'] ?? $details['ccType'] ?? '');
				$maskedNumber = (string)($details['maskedCC'] ?? $details['masked_cc'] ?? '');
				$expirationDate = (string)($details['expirationDate'] ?? $details['expiration_date'] ?? '');

				if ($cardType === '' || $maskedNumber === '' || $expirationDate === '') {
					continue;
				}

				$displayLabel = trim((string)preg_replace('/\s+/', ' ', sprintf('%s %s %s', $cardType, $maskedNumber, $expirationDate)));
				$vaultTokens[] = [
					'public_hash' => $publicHash,
					'label' => $displayLabel !== '' ? $displayLabel : $publicHash,
				];
			}
		} catch (\Throwable) {
			$vaultTokens = [];
		}

		$this->vaultTokensCacheByCustomerId[$customerId] = $vaultTokens;
		return $vaultTokens;
	}

	public function getAdminCancelUrl(): string
	{
		return $this->getUrl('fiserv/subscription/cancel');
	}

	public function getAdminOrderUrlByIncrement(string $incrementId): string
	{
		if ($incrementId === '') {
			return '';
		}

		try {
			$salesOrder = $this->getOrderByIncrementId($incrementId);
			if ($salesOrder) {
				return $this->getUrl('sales/order/view', ['order_id' => $salesOrder->getEntityId()]);
			}
		} catch (\Throwable) {
		}

		return '';
	}

	/**
	 * Returns the entity_id of the most recently created subscription row in this chain.
	 */
	public function getLatestSubscriptionIdByChain($subscriptionRow): int
	{
		if (!$subscriptionRow) {
			return 0;
		}

		try {
			$chainKey = (string)($subscriptionRow->getData('original_order_increment')
				?: $subscriptionRow->getData('order_increment_id')
					?: '');

			if ($chainKey === '') {
				return 0;
			}

			$chainKey = preg_replace('/-\d+$/', '', $chainKey) ?: $chainKey;

			$latestRowCollection = $this->subscriptionOrderFactory->create()->getCollection();
			$latestRowCollection->addFieldToFilter('original_order_increment', ['eq' => $chainKey]);
			$latestRowCollection->setOrder('created_at', 'DESC');
			$latestRowCollection->setPageSize(1);

			$latestRow = $latestRowCollection->getFirstItem();
			return ($latestRow && $latestRow->getEntityId()) ? (int)$latestRow->getEntityId() : 0;
		} catch (\Throwable) {
			return 0;
		}
	}

	/**
	 * For the parent (FIRST) row, compute the original first scheduled billing time:
	 *   created_at + interval, truncated to the minute.
	 * This is stable and never changes, unlike next_billing_datetime which advances
	 * each renewal cycle for the cron.
	 */
	public function getFirstNextBillingForHead($subscriptionRow): string
	{
		if (!$subscriptionRow) {
			return '';
		}
		try {
			$createdAt = (string)($subscriptionRow->getData('created_at') ?? '');
			$intervalValue = (int)($subscriptionRow->getData('interval_value') ?: 1);
			$intervalUnit = strtolower((string)($subscriptionRow->getData('interval_unit') ?: 'minute'));

			if ($createdAt === '') {
				return '';
			}

			$allowedUnits = ['minute', 'day', 'week', 'month', 'year'];
			if (!in_array($intervalUnit, $allowedUnits, true)) {
				$intervalUnit = 'minute';
			}

			$billingDateTime = new \DateTime($createdAt, new \DateTimeZone('UTC'));
			$billingDateTime->modify('+' . $intervalValue . ' ' . $intervalUnit);
			$billingDateTime->setTime((int)$billingDateTime->format('H'), (int)$billingDateTime->format('i'), 0);

			return $billingDateTime->format('Y-m-d H:i:s');
		} catch (\Throwable $exception) {
			return '';
		}
	}

	public function getChainCancelledForSubscription($subscriptionRow): bool
	{
		if (!$subscriptionRow) {
			return false;
		}

		try {
			$chainKey = (string)($subscriptionRow->getData('original_order_increment')
				?: $subscriptionRow->getData('order_increment_id')
					?: '');

			if ($chainKey === '') {
				return false;
			}

			$chainKey = preg_replace('/-\d+$/', '', $chainKey) ?: $chainKey;

			$firstRowCollection = $this->subscriptionOrderFactory->create()->getCollection();
			$firstRowCollection->addFieldToFilter('original_order_increment', ['eq' => $chainKey]);
			$firstRowCollection->addFieldToFilter('sequence', ['eq' => 'FIRST']);
			$firstRowCollection->setPageSize(1);

			$headRow = $firstRowCollection->getFirstItem();

			// Fallback: head row has empty original_order_increment, find it by order_increment_id directly
			if (!$headRow || !$headRow->getEntityId()) {
				$fallbackCollection = $this->subscriptionOrderFactory->create()->getCollection();
				$fallbackCollection->addFieldToFilter('order_increment_id', ['eq' => $chainKey]);
				$fallbackCollection->setPageSize(1);
				$headRow = $fallbackCollection->getFirstItem();
			}

			// Chain is ended when the head row is no longer 'active'
			return $headRow && strtolower((string)$headRow->getStatus()) !== 'active';
		} catch (\Throwable) {
			return false;
		}
	}

	/**
	 * Returns the pending_card_label stored on the chain-head row, or empty string.
	 * Used in the admin phtml to show "Updated. Future renewals will use this card."
	 * on initial server-render — server-persisted so it reflects changes from
	 * both the customer and admin pages.
	 */
	public function getPendingCardLabelForSubscription($subscriptionRow): string
	{
		if (!$subscriptionRow) {
			return '';
		}

		try {
			$chainKey = (string)($subscriptionRow->getData('original_order_increment')
				?: $subscriptionRow->getData('order_increment_id')
					?: '');

			if ($chainKey === '') {
				return '';
			}

			$chainKey = preg_replace('/-\d+$/', '', $chainKey) ?: $chainKey;

			$firstRowCollection = $this->subscriptionOrderFactory->create()->getCollection();
			$firstRowCollection->addFieldToFilter('original_order_increment', ['eq' => $chainKey]);
			$firstRowCollection->addFieldToFilter('sequence', ['eq' => 'FIRST']);
			$firstRowCollection->setPageSize(1);

			$headRow = $firstRowCollection->getFirstItem();
			if (!$headRow || !$headRow->getEntityId()) {
				return '';
			}

			return (string)($headRow->getData('pending_card_label') ?? '');
		} catch (\Throwable) {
			return '';
		}
	}
}