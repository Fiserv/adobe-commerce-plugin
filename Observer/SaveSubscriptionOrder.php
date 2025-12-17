<?php
declare(strict_types=1);

namespace Fiserv\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ResourceConnection;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Subscription\Order as SubscriptionOrderModel;
use Fiserv\Payments\Model\Subscription\OrderFactory;
use Fiserv\Payments\Model\SubscriptionOrder\SubscriptionOrderRepository;
use Fiserv\Payments\Observer\CommerceHub\DataAssignObserver as Keys;

class SaveSubscriptionOrder implements ObserverInterface
{
	private const SKIP_RECURRING_FLAG_KEY = 'is_recurring_order';
	private const JOB_CODE = 'fiserv_process_subscriptions';

	public function __construct(
		private readonly OrderFactory $subscriptionOrderFactory,
		private readonly SubscriptionOrderRepository $subscriptionOrderRepository,
		private readonly MultiLevelLogger $logger,
		private readonly ResourceConnection $resource
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
			$this->scheduleCronAt($subscription->getNextBillingDatetime(), $intervalUnit);
		} catch (\Throwable $exception) {
			$this->logger->logError(1, 'Failed creating subscription', $exception->getMessage());
		}
	}

	private function loadByOrderIncrement(string $orderIncrement): ?SubscriptionOrderModel
	{
		try {
			$model = $this->subscriptionOrderFactory->create()->load($orderIncrement, 'order_increment_id');
			return ($model && $model->getEntityId()) ? $model : null;
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

	/**
	 * Insert a precise cron_schedule row so ProcessSubscriptions fires at exactly
	 * the subscription's next_billing_datetime rather than waiting for a polling sweep.
	 *
	 * Only done for minute-level intervals — for day/week/month/year the regular
	 * sweeper (crontab * * * * *) combined with the next_billing_datetime <= NOW()
	 * filter is sufficient, and Magento purges cron_schedule entries older than a
	 * few days so a 2-week entry would be deleted before it ever fires.
	 */
	private function scheduleCronAt(string $nextBillingDatetime, string $intervalUnit = 'minute'): void
	{
		// For non-minute intervals, the per-minute sweeper handles it — skip.
		if ($intervalUnit !== 'minute') {
			$this->logger->logInfo(1, 'Scheduled subscription billing run (sweeper)', "next_billing_datetime: {$nextBillingDatetime}");
			return;
		}

		try {
			$currentUtcTime = new \DateTime('now', new \DateTimeZone('UTC'));
			$billingDateTime = new \DateTime($nextBillingDatetime, new \DateTimeZone('UTC'));

			// If already past-due (e.g. server was down), fire as soon as possible
			if ($billingDateTime < $currentUtcTime) {
				$billingDateTime = clone $currentUtcTime;
			}

			// Truncate to the minute floor.
			// Only bump +1 minute if seconds >= 30 — meaning the cron runner for
			// that minute has very likely already fired (~:03), so we'd miss it.
			$secondsIntoMinute = (int)$billingDateTime->format('s');
			$billingDateTime->setTime((int)$billingDateTime->format('H'), (int)$billingDateTime->format('i'), 0);
			if ($secondsIntoMinute >= 30) {
				$billingDateTime->modify('+1 minute');
			}
			$scheduledAt = $billingDateTime->format('Y-m-d H:i:s');

			$connection = $this->resource->getConnection();
			$cronTable = $this->resource->getTableName('cron_schedule');

			// Guard: don't insert a duplicate for the same minute
			$existingCount = (int)$connection->fetchOne(
				"SELECT COUNT(*) FROM `{$cronTable}`
				 WHERE job_code = :job_code
				   AND scheduled_at = :scheduled_at
				   AND status IN ('pending', 'running')",
				[':job_code' => self::JOB_CODE, ':scheduled_at' => $scheduledAt]
			);

			if ($existingCount > 0) {
				$this->logger->logInfo(2, 'Subscription cron entry already exists', "scheduled_at: {$scheduledAt}");
				return;
			}

			$connection->insert($cronTable, [
				'job_code' => self::JOB_CODE,
				'status' => 'pending',
				'messages' => '',
				'created_at' => $currentUtcTime->format('Y-m-d H:i:s'),
				'scheduled_at' => $scheduledAt,
			]);

			$this->logger->logInfo(1, 'Scheduled subscription billing run', "scheduled_at: {$scheduledAt}");
		} catch (\Throwable $exception) {
			$this->logger->logError(1, 'Failed to schedule subscription cron entry', $exception->getMessage());
		}
	}
}
