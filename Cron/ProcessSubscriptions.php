<?php
declare(strict_types=1);
namespace Fiserv\Payments\Cron;

use Fiserv\Payments\Model\ResourceModel\Subscription\Order\CollectionFactory;
use Fiserv\Payments\Service\SubscriptionProcessor;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Subscription\Order;
use Fiserv\Payments\Model\Subscription\OrderFactory;
use DateTime;
use DateTimeZone;
use Throwable;

/**
 * Runs on demand via precise cron_schedule entries inserted by SaveSubscriptionOrder
 * and SubscriptionProcessor. Only processes rows where next_billing_datetime <= NOW().
 * Produces no log output when nothing is due, so long-interval subscriptions
 * (monthly, yearly) do not flood the log file.
 *
 * REQUIREMENT: the system crontab on the Magento server must contain:
 *   * * * * * php /var/www/html/bin/magento cron:run 2>&1
 * or be installed via: php bin/magento cron:install
 */
class ProcessSubscriptions
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly SubscriptionProcessor $subscriptionProcessor,
        private readonly MultiLevelLogger $logger,
        private readonly OrderFactory $orderFactory
    ) {}

    public function execute(): void
    {
        try {
            $this->resetStaleProcessingRows();

            // Check whether any subscriptions are actually due before logging anything.
            $dueSubscriptionCollection = $this->collectionFactory->create()->getActiveSubscriptionsDueForBilling();
            $dueSubscriptionCount = $dueSubscriptionCollection->getSize();

            // Nothing to do — exit silently. No log output at all.
            if ($dueSubscriptionCount === 0) {
                return;
            }

            // Only log when there is real work to perform.
            $this->logger->logInfo(1, "=== Subscription Cron Started ===", "");
            $this->logger->logInfo(1, "Found subscriptions to process", "Count: {$dueSubscriptionCount}");

            $resourceModel = null;
            $dbConnection = null;
            $tableName = null;
            $successCount = 0;
            $failCount = 0;

            foreach ($dueSubscriptionCollection as $dueSubscription) {
                try {
                    if ($dbConnection === null) {
                        $resourceModel = $dueSubscription->getResource();
                        $dbConnection = $resourceModel->getConnection();
                        $tableName = $resourceModel->getMainTable();
                    }

                    // Atomic claim: flip status to 'processing' only if still active.
                    // Guards against two cron workers racing on the same row.
                    $rowsClaimed = (int)$dbConnection->update($tableName, ['status' => 'processing'], [
                        'entity_id = ?' => (int)$dueSubscription->getEntityId(),
                        'status = ?' => Order::STATUS_ACTIVE,
                    ]);

                    if ($rowsClaimed !== 1) {
                        $this->logger->logInfo(2, "Skipping subscription (already claimed)", "ID: {$dueSubscription->getId()}");
                        continue;
                    }

                    $claimedSubscription = $this->orderFactory->create()->load($dueSubscription->getEntityId());
                    $this->logger->logInfo(1, "Processing subscription (claimed)", "ID: {$claimedSubscription->getId()}, Next Billing: {$claimedSubscription->getNextBillingDatetime()}");

                    try {
                        $this->subscriptionProcessor->processSubscription($claimedSubscription);
                        $successCount++;
                    } catch (Throwable $exception) {
                        $failCount++;
                        $this->logger->logError(1, "Subscription processing failed", "ID: {$claimedSubscription->getId()}, Error: {$exception->getMessage()}");
                        try {
                            $this->orderFactory->create()->load($claimedSubscription->getEntityId())
                                ->setStatus(Order::STATUS_ACTIVE)->save();
                        } catch (Throwable $ignored) {}
                    }
                } catch (Throwable $exception) {
                    $failCount++;
                    $this->logger->logError(1, "Unexpected error in cron loop", $exception->getMessage());
                }
            }

            $this->logger->logInfo(1, "=== Subscription Cron Completed ===", "Processed: {$dueSubscriptionCount}, Success: {$successCount}, Failed: {$failCount}");

        } catch (Throwable $exception) {
            $this->logger->logCritical(1, "Critical error in subscription cron", "Error: {$exception->getMessage()}");
        }
    }

    /**
     * Silently resets any subscription rows stuck in 'processing' for more than
     * 15 minutes back to 'active' so they can be retried on the next cron run.
     * Runs on every execution but produces no log output unless a row is actually reset.
     */
    private function resetStaleProcessingRows(): void
    {
        try {
            $cutoff = (new DateTime('-15 minutes', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('status', 'processing');
            $collection->addFieldToFilter('updated_at', ['lt' => $cutoff]);

            foreach ($collection as $staleSubscription) {
                try {
                    $this->orderFactory->create()
                        ->load($staleSubscription->getEntityId())
                        ->setStatus(Order::STATUS_ACTIVE)
                        ->save();
                    $this->logger->logInfo(2, "Reset stuck subscription to active", "ID: {$staleSubscription->getId()}");
                } catch (Throwable $ignored) {}
            }
        } catch (Throwable $ignored) {}
    }
}