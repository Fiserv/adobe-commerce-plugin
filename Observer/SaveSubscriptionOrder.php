<?php
declare(strict_types=1);

namespace Fiserv\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Subscription\Order as SubscriptionOrderModel;
use Fiserv\Payments\Model\Subscription\OrderFactory;
use Fiserv\Payments\Model\SubscriptionOrder\SubscriptionOrderRepository;
use Fiserv\Payments\Observer\CommerceHub\DataAssignObserver as Keys;
use Fiserv\Payments\Service\CronScheduler;

class SaveSubscriptionOrder implements ObserverInterface
{
	private const SKIP_RECURRING_FLAG_KEY = 'is_recurring_order';

	public function __construct(
		private readonly OrderFactory $subscriptionOrderFactory,
		private readonly SubscriptionOrderRepository $subscriptionOrderRepository,
		private readonly MultiLevelLogger $logger,
		private readonly CronScheduler $cronScheduler
	) {}

	public function execute(Observer $observer): void
	{
		$order = $observer->getEvent()->getOrder();
		if (!$order) {
			return;
		}

		try {
			$payment = $order->getPayment();
		} catch (\Throwable) {
			return;
		}

		if (!$payment || !method_exists($payment, 'getAdditionalInformation')) {
			return;
		}

		// Skip cron-generated recurring orders (handled by SubscriptionProcessor)
		$isRecurringOrder = (bool)($payment->getAdditionalInformation(self::SKIP_RECURRING_FLAG_KEY) ?? false);
		if ($isRecurringOrder) {
			return;
		}

		// Only process orders explicitly flagged as subscription at checkout
		$isSubscription = (bool)($payment->getAdditionalInformation(Keys::IS_SUBSCRIPTION_KEY) ?? false);
		if (!$isSubscription) {
			return;
		}

		$orderIncrement = (string)($order->getIncrementId() ?: $order->getEntityId());

		$token = (string)($payment->getAdditionalInformation(Keys::PAYMENT_TOKEN_KEY) ?? '');
		$schemeRef = (string)($payment->getAdditionalInformation(Keys::SCHEME_REFERENCE_TRANSACTION_ID_KEY) ?? '');
		$expMonth = (string)($payment->getAdditionalInformation(Keys::EXP_MONTH_KEY) ?? '');
		$expYear = (string)($payment->getAdditionalInformation(Keys::EXP_YEAR_KEY) ?? '');

		$intervalValue = $payment->getAdditionalInformation(Keys::SUBSCRIPTION_INTERVAL_VALUE_KEY);
		$intervalUnit = $payment->getAdditionalInformation(Keys::SUBSCRIPTION_INTERVAL_UNIT_KEY);

		// Both interval fields must be present — skip if this is just a regular order
		if ($intervalValue === null || $intervalValue === '' || $intervalUnit === null || $intervalUnit === '') {
			$this->logger->logInfo(1, 'SaveSubscriptionOrder: skipping — interval not set', "Order ID:{$orderIncrement}");
			return;
		}

		$intervalValue = max(1, (int)$intervalValue);
		$intervalUnit = $this->normalizeIntervalUnit((string)$intervalUnit);

		[$customerId, $customerEmail, $customerName] = $this->extractOrderCustomerInfo($order);

		try {
			$subscription = $this->loadByOrderIncrement($orderIncrement)
				?: $this->subscriptionOrderFactory->create();

			$subscription->setOrderIncrementId($orderIncrement);
			$subscription->setStatus(SubscriptionOrderModel::STATUS_ACTIVE);
			$subscription->setIsActive(1);
			$subscription->setSequence('FIRST');
			$subscription->setOriginalOrderIncrement($orderIncrement);
			$subscription->setIntervalValue($intervalValue);
			$subscription->setIntervalUnit($intervalUnit);
			$subscription->setNextBillingDatetime($this->computeNextBillingDatetime($intervalValue, $intervalUnit));

			if ($token !== '')     { $subscription->setPaymentToken($token); }
			if ($schemeRef !== '') { $subscription->setSchemeReferenceTransactionId($schemeRef); }
			if ($expMonth !== '')  { $subscription->setExpirationMonth($expMonth); }
			if ($expYear !== '')   { $subscription->setExpirationYear($expYear); }

			if (!$subscription->getCustomerId() && $customerId !== null) {
				$subscription->setCustomerId($customerId);
			}
			if (!$subscription->getCustomerEmail() && $customerEmail !== null) {
				$subscription->setCustomerEmail($customerEmail);
			}
			if (!$subscription->getCustomerName() && $customerName !== '') {
				$subscription->setCustomerName($customerName);
			}

			$subscription->setFailedAttempts(0);
			$this->subscriptionOrderRepository->save($subscription);

			$this->logger->logInfo(1, 'Subscription created', "Order ID:{$orderIncrement}");

			// Insert a precise cron_schedule entry so ProcessSubscriptions fires
			// exactly at next_billing_datetime — not on a constant polling loop.
			$this->cronScheduler->scheduleCronAt($subscription->getNextBillingDatetime(), $intervalUnit);
		} catch (\Throwable $exception) {
			$this->logger->logError(1, 'Failed creating subscription', $exception->getMessage());
		}
	}

	private function loadByOrderIncrement(string $orderIncrement): ?SubscriptionOrderModel
	{
		try {
			return $this->subscriptionOrderRepository->getByOrderIncrementId($orderIncrement) ?: null;
		} catch (\Throwable) {
			return null;
		}
	}

	private function extractOrderCustomerInfo($order): array
	{
		$customerId = null;
		$customerEmail = null;
		$customerName = '';
		try {
			$customerId = $order->getCustomerId() ?: null;
			$customerEmail = $order->getCustomerEmail() ?: null;
			$customerName = (string)$order->getCustomerName();
			if ($customerName === '') {
				$firstName = method_exists($order, 'getCustomerFirstname') ? (string)$order->getCustomerFirstname() : '';
				$lastName = method_exists($order, 'getCustomerLastname') ? (string)$order->getCustomerLastname() : '';
				$customerName = trim($firstName . ' ' . $lastName);
			}
			if ($customerName === '') { $customerName = 'Guest'; }
		} catch (\Throwable) {}
		return [$customerId, $customerEmail, $customerName];
	}

	private function normalizeIntervalUnit(string $unit): string
	{
		$normalized = strtolower(trim($unit));
		return match (true) {
			in_array($normalized, ['min', 'mins', 'minute', 'minutes'], true) => 'minute',
			in_array($normalized, ['day', 'days', 'd'], true) => 'day',
			in_array($normalized, ['week', 'weeks', 'w', 'weekly'], true) => 'week',
			in_array($normalized, ['month', 'months', 'monthly'], true) => 'month',
			in_array($normalized, ['year', 'years', 'y', 'annual', 'yearly'], true) => 'year',
			default => 'minute',
		};
	}

	private function computeNextBillingDatetime(int $intervalValue, string $intervalUnit): string
	{
		$nowUtc = new \DateTime('now', new \DateTimeZone('UTC'));
		if ($intervalUnit === 'minute') {
			$nowUtc->modify('+' . ($intervalValue * 60) . ' seconds');
		} else {
			$nowUtc->modify(sprintf('+%d %s', $intervalValue, $intervalUnit));
		}
		// Truncate to the minute boundary so the cron runner (which fires at ~:03)
		// always sees next_billing_datetime as already due when it runs.
		// e.g. now=16:37:09, +1min → 16:38:09 → truncate → 16:38:00 ✓
		// The 16:38:03 cron run: 16:38:00 <= 16:38:03 → processes immediately.
		$nowUtc->setTime((int)$nowUtc->format('H'), (int)$nowUtc->format('i'), 0);
		return $nowUtc->format('Y-m-d H:i:s');
	}

}

