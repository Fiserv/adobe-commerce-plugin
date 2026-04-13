<?php
declare(strict_types=1);

namespace Fiserv\Payments\Service;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\App\ResourceConnection;

/**
 * Inserts precise cron_schedule entries so ProcessSubscriptions fires at exactly
 * the subscription's next_billing_datetime.
 *
 * Only inserts entries for minute-level intervals.
 * For day/week/month/year intervals the regular per-minute sweeper combined with
 * the next_billing_datetime <= NOW() filter in the collection is sufficient, and
 * Magento purges cron_schedule entries older than a few days — so a 2-week-ahead
 * entry would be deleted before it ever fires.
 */
class CronScheduler
{
	private const JOB_CODE = 'fiserv_process_subscriptions';

	public function __construct(
		private readonly ResourceConnection $resource,
		private readonly MultiLevelLogger $logger
	) {}

	/**
	 * Schedule a cron_schedule entry at the given UTC datetime.
	 *
	 * @param string $nextBillingDatetime UTC datetime string (Y-m-d H:i:s).
	 * @param string $intervalUnit        minute | day | week | month | year
	 */
	public function scheduleCronAt(string $nextBillingDatetime, string $intervalUnit = 'minute'): void
	{
		// For non-minute intervals the per-minute sweeper handles billing — no entry needed.
		if ($intervalUnit !== 'minute') {
			$this->logger->logInfo(1, 'CronScheduler: sweeper handles next billing', "next_billing_datetime: {$nextBillingDatetime}");
			return;
		}

		try {
			$currentUtcTime = new \DateTime('now', new \DateTimeZone('UTC'));
			$billingDateTime = new \DateTime($nextBillingDatetime, new \DateTimeZone('UTC'));

			// If already past-due (e.g. server was down), fire as soon as possible.
			if ($billingDateTime < $currentUtcTime) {
				$billingDateTime = clone $currentUtcTime;
			}

			// Truncate to minute floor.
			// Only bump +1 minute if seconds >= 30 — the cron runner for that minute has
			// very likely already fired (~:03), so we'd miss the exact slot.
			$secondsIntoMinute = (int)$billingDateTime->format('s');
			$billingDateTime->setTime((int)$billingDateTime->format('H'), (int)$billingDateTime->format('i'), 0);
			if ($secondsIntoMinute >= 30) {
				$billingDateTime->modify('+1 minute');
			}
			$scheduledAt = $billingDateTime->format('Y-m-d H:i:s');

			$connection = $this->resource->getConnection();
			$cronTable  = $this->resource->getTableName('cron_schedule');

			// Guard: don't insert a duplicate for the same job + minute slot.
			$existingCount = (int)$connection->fetchOne(
				"SELECT COUNT(*) FROM `{$cronTable}`
				 WHERE job_code    = :job_code
				   AND scheduled_at = :scheduled_at
				   AND status IN ('pending', 'running')",
				[':job_code' => self::JOB_CODE, ':scheduled_at' => $scheduledAt]
			);

			if ($existingCount > 0) {
				$this->logger->logInfo(2, 'CronScheduler: entry already exists', "scheduled_at: {$scheduledAt}");
				return;
			}

			$connection->insert($cronTable, [
				'job_code'     => self::JOB_CODE,
				'status'       => 'pending',
				'messages'     => '',
				'created_at'   => $currentUtcTime->format('Y-m-d H:i:s'),
				'scheduled_at' => $scheduledAt,
			]);

			$this->logger->logInfo(1, 'CronScheduler: scheduled billing run', "scheduled_at: {$scheduledAt}");
		} catch (\Throwable $e) {
			$this->logger->logError(1, 'CronScheduler: failed to insert cron entry', $e->getMessage());
		}
	}
}

