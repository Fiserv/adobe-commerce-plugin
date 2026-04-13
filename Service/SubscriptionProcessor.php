<?php
declare(strict_types=1);

namespace Fiserv\Payments\Service;

use DateTime;
use DateTimeZone;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Subscription\Order;
use Fiserv\Payments\Model\SubscriptionOrder\SubscriptionOrderRepository;
use Fiserv\Payments\Observer\CommerceHub\DataAssignObserver;
use Fiserv\Payments\Service\CronScheduler;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Reorder\Reorder as MagentoReorder;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Throwable;

/**
 * Executes one recurring billing cycle for a subscription chain.
 *
 * Called by ProcessSubscriptions cron when next_billing_datetime is due.
 * Flow: clone cart → resolve vault token → charge CommerceHub → submit order
 *       → stamp child row with next billing time → schedule next cron entry
 *       → restore head row's original next_billing_datetime.
 */
class SubscriptionProcessor
{
	public function __construct(
		private readonly SubscriptionOrderRepository     $subscriptionRepository,
		private readonly OrderRepositoryInterface        $orderRepository,
		private readonly MagentoReorder                  $magentoReorder,
		private readonly PaymentProcessor                $paymentProcessor,
		private readonly MultiLevelLogger                $logger,
		private readonly SearchCriteriaBuilder           $searchCriteriaBuilder,
		private readonly PaymentTokenManagementInterface $paymentTokenManagement,
		private readonly CartRepositoryInterface         $quoteRepository,
		private readonly ExtensionAttributesFactory      $extensionAttributesFactory,
		private readonly QuoteManagement                 $quoteManagement,
		private readonly ResourceConnection              $resource,
		private readonly CronScheduler                   $cronScheduler
	)
	{
	}

	/**
	 * Process one renewal for the given subscription row.
	 *
	 * @param Order $subscription Head (FIRST) row to bill.
	 * @throws Throwable Re-thrown after cleanup so the cron can log it.
	 */
	public function processSubscription(Order $subscription): void
	{
		/** @var Quote|null $renewalQuote Kept in scope so cleanup runs on every failure path. */
		$renewalQuote = null;

		try {
			$previousOrderIncrementId = (string)$subscription->getOrderIncrementId();
			if ($previousOrderIncrementId === '') {
				throw new NoSuchEntityException(
					__('No order_increment_id on subscription ID %1', $subscription->getId())
				);
			}

			$subscriptionChainHead = $this->subscriptionRepository->getChainHead($subscription);
			$parentOrder = $this->loadOrderByIncrementId($previousOrderIncrementId);

			// Clone the previous order's cart (items, quantities, addresses).
			$reorderOutput = $this->magentoReorder->execute($previousOrderIncrementId, (string)$parentOrder->getStoreId());
			$renewalQuote = $reorderOutput->getCart();

			// Prevent cart contamination from other open quotes for this customer.
			$customerId = (int)($parentOrder->getCustomerId() ?: 0);
			if ($customerId > 0 && $renewalQuote && $renewalQuote->getId()) {
				$this->deactivateOtherActiveQuotesForCustomer($customerId, (int)$renewalQuote->getId());
			}

			if (!empty($reorderOutput->getErrors())) {
				foreach ($reorderOutput->getErrors() as $reorderError) {
					$this->logger->logError(1, 'Recurring reorder failed',
						method_exists($reorderError, 'getMessage') ? (string)$reorderError->getMessage() : 'Unknown reorder error');
				}
				$this->cleanupRenewalQuoteSafely($renewalQuote);
				return;
			}

			try {
				$renewalQuote->setStoreId((int)$parentOrder->getStoreId());
			} catch (Throwable $ignored) {
			}

			// Resolve shipping method (skipped for virtual products).
			if ($renewalQuote && !$renewalQuote->isVirtual()) {
				$shippingAddress = $renewalQuote->getShippingAddress();
				if (!$shippingAddress) {
					throw new \RuntimeException('Renewal quote missing shipping address');
				}
				$shippingAddress->setCollectShippingRates(true)->collectShippingRates();
				$parentShippingMethod = (string)($parentOrder->getShippingMethod() ?: '');
				$currentShippingMethod = (string)($shippingAddress->getShippingMethod() ?: '');
				if ($currentShippingMethod === '' && $parentShippingMethod !== '') {
					$shippingAddress->setShippingMethod($parentShippingMethod);
					$currentShippingMethod = $parentShippingMethod;
				}
				$availableShippingMethods = array_map(
					fn($rate) => $rate->getCarrier() . '_' . $rate->getMethod(),
					$shippingAddress->getAllShippingRates()
				);
				if ($currentShippingMethod === '' || (!empty($availableShippingMethods) && !in_array($currentShippingMethod, $availableShippingMethods, true))) {
					if (empty($availableShippingMethods)) {
						throw new \RuntimeException('No shipping rates available for renewal quote');
					}
					$shippingAddress->setShippingMethod($availableShippingMethods[0]);
				}
				$shippingAddress->setCollectShippingRates(true);
			}

			$quotePayment = $renewalQuote->getPayment();
			if (!$quotePayment) {
				throw new \RuntimeException('Reorder quote has no payment object');
			}

			// Flag as recurring so downstream request builders apply stored-credential rules.
			$quotePayment->setAdditionalInformation('is_recurring_order', true);
			$quotePayment->setMethod('commercehub');
			$quotePayment->setAdditionalInformation('is_subscription', true);
			$quotePayment->setAdditionalInformation(
				'scheme_reference_transaction_id', (string)$subscriptionChainHead->getSchemeReferenceTransactionId()
			);

			// Resolve root increment: parent payment meta → subscription row → previous ID, strip suffix.
			$rootOrderIncrement = null;
			try {
				$parentOrderPayment = $parentOrder->getPayment();
				if ($parentOrderPayment && method_exists($parentOrderPayment, 'getAdditionalInformation')) {
					$rootOrderIncrement = $parentOrderPayment->getAdditionalInformation('original_order_increment')
						?: $parentOrderPayment->getAdditionalInformation('originalOrderIncrement');
				}
			} catch (Throwable $ignored) {
			}

			if (!$rootOrderIncrement) {
				$rootOrderIncrement = (string)($subscription->getOriginalOrderIncrement() ?: '') ?: $previousOrderIncrementId;
			}
			$rootOrderIncrement = preg_replace('/-\d+$/', '', (string)$rootOrderIncrement) ?: (string)$rootOrderIncrement;

			$quotePayment->setAdditionalInformation('original_order_increment', $rootOrderIncrement);
			$quotePayment->setAdditionalInformation('originalOrderIncrement', $rootOrderIncrement);

			$newOrderIncrementId = $rootOrderIncrement . '-' . $this->getNextSuffixForRootFromSalesOrder($rootOrderIncrement);
			$renewalQuote->setReservedOrderId($newOrderIncrementId);
			try {
				$this->quoteRepository->save($renewalQuote);
			} catch (Throwable $ignored) {
			}

			$this->attachMerchantOrderId($quotePayment, $newOrderIncrementId);
			$quotePayment->setAdditionalInformation('merchantOrderId', $newOrderIncrementId);

			// Resolve vault public_hash from the stored TransArmor gateway token.
			$storedPaymentToken = (string)$subscriptionChainHead->getPaymentToken();
			if ($storedPaymentToken === '') {
				$this->logger->logError(1, 'Subscription payment failed', 'No payment token on chain head');
				$this->cleanupRenewalQuoteSafely($renewalQuote);
				return;
			}
			$customerIdForVault = $parentOrder->getCustomerId() ? (int)$parentOrder->getCustomerId() : null;
			$vaultPublicHash = $this->resolveVaultPublicHash($storedPaymentToken, $customerIdForVault);
			if ($vaultPublicHash === '') {
				$this->logger->logError(1, 'Subscription payment failed',
					'Could not resolve vault public_hash from token: ' . $storedPaymentToken);
				$this->cleanupRenewalQuoteSafely($renewalQuote);
				return;
			}

			$quotePayment->setAdditionalInformation('public_hash', $vaultPublicHash);
			$quotePayment->setAdditionalInformation(DataAssignObserver::PAYMENT_TOKEN_KEY, $vaultPublicHash);
			$this->tryAttachVaultTokenAndExpiry($quotePayment, $customerIdForVault, $vaultPublicHash);

			$renewalQuote->collectTotals();
			try {
				$this->quoteRepository->save($renewalQuote);
			} catch (Throwable $ignored) {
			}

			$paymentResult = $this->paymentProcessor->processSubscriptionPayment($renewalQuote, $subscription);
			if (empty($paymentResult['success'])) {
				$this->logger->logError(1, 'Subscription payment failed', $paymentResult['error'] ?? 'Unknown error');
				$this->cleanupRenewalQuoteSafely($renewalQuote);
				return;
			}

			$newOrder = $this->quoteManagement->submit($renewalQuote);
			if (!$newOrder) {
				throw new \RuntimeException('Failed to create renewal order from quote');
			}

			// Write approved amount + transaction ID to the order's status history.
			try {
				$transactionId = $paymentResult['transaction_id'] ?? null;
				if (!$transactionId) {
					$newOrderPayment = $newOrder->getPayment();
					$transactionId = $newOrderPayment ? ($newOrderPayment->getLastTransId() ?: null) : null;
				}
				$newOrder->addCommentToStatusHistory(sprintf(
					'Authorized amount of $%s. Transaction ID: "%s"',
					number_format((float)$newOrder->getGrandTotal(), 2),
					$transactionId ?? 'N/A'
				));
				$this->orderRepository->save($newOrder);
			} catch (Throwable $e) {
				$this->logger->logError(2, 'Failed to add transaction comment', $e->getMessage());
			}

			$this->cleanupRenewalQuoteSafely($renewalQuote);

			// Compute next billing datetime: now + interval, truncated to the minute.
			$nextBillingDatetime = '';
			try {
				$headForInterval = $this->subscriptionRepository->getChainHead($subscription);
				$intervalValue = (int)($headForInterval->getIntervalValue() ?: 1);
				$intervalUnit = strtolower((string)($headForInterval->getIntervalUnit() ?: 'minute'));
				if (!in_array($intervalUnit, ['minute', 'day', 'week', 'month', 'year'], true)) {
					$intervalUnit = 'minute';
				}
				$nextBillingTime = new DateTime('now', new DateTimeZone('UTC'));
				$nextBillingTime->modify('+' . $intervalValue . ' ' . $intervalUnit);
				$nextBillingTime->setTime((int)$nextBillingTime->format('H'), (int)$nextBillingTime->format('i'), 0);
				$nextBillingDatetime = $nextBillingTime->format('Y-m-d H:i:s');
			} catch (Throwable $ignored) {
			}

			try {
				$this->cronScheduler->scheduleCronAt($nextBillingDatetime, $intervalUnit ?? 'minute');
			} catch (Throwable $e) {
				$this->logger->logError(1, 'Failed to schedule next cron', $e->getMessage());
			}

			// Update the child row: mark processed, stamp next_billing_datetime, backfill customer/interval data.
			try {
				$newOrderIncrementIdStr = (string)$newOrder->getIncrementId();
				if ($newOrderIncrementIdStr !== '') {
					$renewalRow = $this->subscriptionRepository->getByOrderIncrementId($newOrderIncrementIdStr);
					if ($renewalRow && $renewalRow->getEntityId()) {
						$renewalRow->setStatus(Order::STATUS_PROCESSED)->setIsActive(1);
						if ($nextBillingDatetime !== '') {
							$renewalRow->setNextBillingDatetime($nextBillingDatetime);
						}
						$headForInterval = $this->subscriptionRepository->getChainHead($subscription);
						$renewalRow->setIntervalValue((int)($headForInterval->getIntervalValue() ?: 1));
						$renewalRow->setIntervalUnit((string)($headForInterval->getIntervalUnit() ?: 'minute'));
						if (!$renewalRow->getCustomerId() && $parentOrder->getCustomerId()) {
							$renewalRow->setCustomerId((int)$parentOrder->getCustomerId());
						}
						if (!$renewalRow->getCustomerEmail() && $parentOrder->getCustomerEmail()) {
							$renewalRow->setCustomerEmail((string)$parentOrder->getCustomerEmail());
						}
						if (!$renewalRow->getCustomerName()) {
							$fullName = trim(
								(string)($parentOrder->getCustomerFirstname() ?: '') . ' ' .
								(string)($parentOrder->getCustomerLastname() ?: '')
							);
							if ($fullName !== '') {
								$renewalRow->setCustomerName($fullName);
							}
						}
						$this->subscriptionRepository->save($renewalRow);
					}
				}
			} catch (Throwable $e) {
				$this->logger->logError(2, 'Failed to mark renewal row as processed', $e->getMessage());
			}

			// Restore head row: keep status=active and lock next_billing_datetime to its original value.
			// Raw SQL restore is intentional — the ORM save above may have dirtied the column.
			// Also clear change_payment_card so the "Updated" notice disappears from both UIs.
			try {
				$subscriptionChainHead = $this->subscriptionRepository->getChainHead($subscription);
				$originalHeadNextBillingDatetime = (string)($subscriptionChainHead->getNextBillingDatetime() ?? '');
				$subscriptionChainHead->setStatus(Order::STATUS_ACTIVE)->setIsActive(1);
				$subscriptionChainHead->setChangePaymentCard(null);
				$this->subscriptionRepository->save($subscriptionChainHead);
				if ($originalHeadNextBillingDatetime !== '') {
					$this->resource->getConnection()->update(
						$this->resource->getTableName('subscription_order'),
						['next_billing_datetime' => $originalHeadNextBillingDatetime],
						['entity_id = ?' => (int)$subscriptionChainHead->getEntityId()]
					);
				}
			} catch (Throwable $e) {
				$this->logger->logError(1, 'Failed to update head subscription status', $e->getMessage());
			}

		} catch (Throwable $e) {
			$this->cleanupRenewalQuoteSafely($renewalQuote);
			$this->logger->logError(1, 'processSubscription failed', $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Deactivate all active quotes for a customer except the renewal quote just created.
	 * Prevents stale carts from being merged into the renewal quote.
	 */
	private function deactivateOtherActiveQuotesForCustomer(int $customerId, int $keepQuoteId): void
	{
		try {
			$this->resource->getConnection()->update(
				$this->resource->getTableName('quote'),
				['is_active' => 0],
				['customer_id = ?' => $customerId, 'is_active = ?' => 1, 'entity_id <> ?' => $keepQuoteId]
			);
		} catch (Throwable $ignored) {
		}
	}

	/**
	 * Deactivate and clear items from a renewal quote so it can never become a customer cart.
	 */
	private function cleanupRenewalQuoteSafely(?Quote $renewalQuote): void
	{
		try {
			if (!$renewalQuote || !$renewalQuote->getId()) {
				return;
			}
			$renewalQuote->setIsActive(false);
			foreach ($renewalQuote->getAllItems() as $item) {
				$renewalQuote->removeItem((int)$item->getId());
			}
			$renewalQuote->setItemsCount(0)->setItemsQty(0);
			$this->quoteRepository->save($renewalQuote);
		} catch (Throwable $ignored) {
		}
	}

	/**
	 * Resolve a Magento vault public_hash from a stored TransArmor gateway token.
	 *
	 * If the stored value is already a 64-char hex hash it is returned directly.
	 * Otherwise the vault_payment_token table is queried by gateway_token prefix,
	 * scoped to the customer to prevent cross-customer token reuse.
	 *
	 * @param string $storedPaymentToken TransArmor token on the subscription row.
	 * @param int|null $customerId Scopes the vault lookup to one customer.
	 * @return string public_hash, or '' if not found.
	 */
	private function resolveVaultPublicHash(string $storedPaymentToken, ?int $customerId): string
	{
		$storedPaymentToken = trim($storedPaymentToken);
		if ($storedPaymentToken === '') {
			return '';
		}
		if (preg_match('/^[a-f0-9]{64}$/i', $storedPaymentToken)) {
			return $storedPaymentToken;
		}

		try {
			$connection = $this->resource->getConnection();
			$tokenTable = $this->resource->getTableName('vault_payment_token');
			$select = $connection->select()
				->from($tokenTable, ['public_hash'])
				->where('is_active = 1')
				->where('payment_method_code = ?', 'fiserv_commercehub');
			if ($customerId) {
				$select->where('customer_id = ?', $customerId);
			}
			$select->where('gateway_token LIKE ?', $storedPaymentToken . '%')->limit(1);
			$row = $connection->fetchRow($select);
			return (!empty($row['public_hash']) && is_string($row['public_hash'])) ? (string)$row['public_hash'] : '';
		} catch (Throwable $ignored) {
			return '';
		}
	}


	/**
	 * Return the next available child-order suffix integer for a root increment.
	 * Queries sales_order as source of truth (e.g. ROOT-1, ROOT-2 → returns 3).
	 *
	 * @param string $rootOrderIncrement Base increment ID with no suffix.
	 * @return int
	 */
	private function getNextSuffixForRootFromSalesOrder(string $rootOrderIncrement): int
	{
		$connection = $this->resource->getConnection();
		$childPrefix = $rootOrderIncrement . '-';
		$prefixLength = strlen($childPrefix);
		$existingIncrementIds = $connection->fetchCol(
			'SELECT increment_id FROM ' . $this->resource->getTableName('sales_order') . ' WHERE increment_id LIKE ?',
			[$childPrefix . '%']
		);
		$highestSuffix = 0;
		foreach ($existingIncrementIds as $incrementId) {
			$suffix = substr((string)$incrementId, $prefixLength);
			if ($suffix !== '' && ctype_digit($suffix)) {
				$highestSuffix = max($highestSuffix, (int)$suffix);
			}
		}
		return $highestSuffix + 1;
	}

	/**
	 * Write the CommerceHub merchant order ID to payment additional info and extension attributes.
	 *
	 * @param mixed $payment
	 * @param string $merchantOrderId e.g. "000007826-2"
	 */
	private function attachMerchantOrderId($payment, string $merchantOrderId): void
	{
		$payment->setAdditionalInformation('merchant_order_id', $merchantOrderId);
		$extensionAttributes = $payment->getExtensionAttributes()
			?: $this->extensionAttributesFactory->create(\Magento\Quote\Api\Data\PaymentInterface::class);
		if ($extensionAttributes && method_exists($extensionAttributes, 'setMerchantOrderId')) {
			$extensionAttributes->setMerchantOrderId($merchantOrderId);
			$payment->setExtensionAttributes($extensionAttributes);
		}
	}

	/**
	 * Attach the vault token and parse card expiry onto the quote payment.
	 *
	 * Expiry is sourced from the vault token's stored JSON details so the
	 * CommerceHub request builders receive the correct month/year.
	 *
	 * @param mixed $payment
	 * @param int|null $customerId
	 * @param string $vaultPublicHash
	 */
	private function tryAttachVaultTokenAndExpiry($payment, $customerId, string $vaultPublicHash): void
	{
		try {
			$vaultToken = $this->paymentTokenManagement->getByPublicHash($vaultPublicHash, $customerId);
			if (!$vaultToken instanceof PaymentTokenInterface) {
				return;
			}

			$extensionAttributes = $payment->getExtensionAttributes()
				?: $this->extensionAttributesFactory->create(\Magento\Quote\Api\Data\PaymentInterface::class);
			if ($extensionAttributes && method_exists($extensionAttributes, 'setVaultPaymentToken')) {
				$extensionAttributes->setVaultPaymentToken($vaultToken);
				$payment->setExtensionAttributes($extensionAttributes);
			}

			$tokenDetailsRaw = $vaultToken->getTokenDetails();
			$tokenDetails = is_string($tokenDetailsRaw) ? @json_decode($tokenDetailsRaw, true)
				: (is_array($tokenDetailsRaw) ? $tokenDetailsRaw : null);
			if (!is_array($tokenDetails)) {
				return;
			}

			$expirationDate = $tokenDetails['expirationDate'] ?? $tokenDetails['expiration_date'] ?? null;
			if (is_string($expirationDate)) {
				$dateParts = preg_split('/[\/\-]/', $expirationDate);
				if (count($dateParts) >= 2) {
					$expiryMonth = str_pad((string)((int)$dateParts[0]), 2, '0', STR_PAD_LEFT);
					$expiryYear = (string)$dateParts[1];
					if ($expiryMonth) {
						$payment->setAdditionalInformation(DataAssignObserver::EXP_MONTH_KEY, $expiryMonth);
					}
					if ($expiryYear) {
						$payment->setAdditionalInformation(DataAssignObserver::EXP_YEAR_KEY, $expiryYear);
					}
				}
			}
		} catch (Throwable $ignored) {
		}
	}

	/**
	 * Load a Magento order by increment ID.
	 *
	 * @param string $orderIncrementId
	 * @return \Magento\Sales\Api\Data\OrderInterface
	 * @throws NoSuchEntityException
	 */
	private function loadOrderByIncrementId(string $orderIncrementId)
	{
		$items = $this->orderRepository->getList(
			$this->searchCriteriaBuilder->addFilter('increment_id', $orderIncrementId, 'eq')->create()
		)->getItems();
		if (!empty($items)) {
			return reset($items);
		}
		throw new NoSuchEntityException(__('Order not found for increment id: %1', $orderIncrementId));
	}
}