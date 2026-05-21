<?php
/**
 * Process Pending Inquiries Observer for Affirm
 * Lazy cron - triggers inquiry processing on frontend requests
 * Ensures inquiry jobs run even if cron is not configured
 */
namespace Fiserv\Payments\Observer\CommerceHub;

use Fiserv\Payments\Cron\PendingInquiryProcessor;
use Fiserv\Payments\Model\PendingInquiryJob;
use Fiserv\Payments\Model\ResourceModel\PendingInquiryJob\CollectionFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProcessPendingInquiriesObserver implements ObserverInterface
{
    /**
     * @var PendingInquiryProcessor
     */
    private $processor;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
     * @var bool
     */
    private static $alreadyProcessed = false;

    /**
     * @param PendingInquiryProcessor $processor
     * @param CollectionFactory $collectionFactory
     * @param MultiLevelLogger $logger
     */
    public function __construct(
        PendingInquiryProcessor $processor,
        CollectionFactory $collectionFactory,
        MultiLevelLogger $logger
    ) {
        $this->processor = $processor;
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
    }

    /**
     * Process pending inquiry jobs on frontend request
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        // Prevent duplicate processing in same request
        if (self::$alreadyProcessed) {
            return;
        }
        self::$alreadyProcessed = true;

        try {
            // Get up to 5 jobs ready to run
            $readyJobs = $this->getReadyJobs(5);
            
            if ($readyJobs->getSize() == 0) {
                return;
            }

            foreach ($readyJobs as $job) {
                try {
                    $this->processor->processJob($job);
                } catch (\Exception $e) {
                    // Log silently
                }
            }
        } catch (\Exception $e) {
            // Log silently
        }
    }

    /**
     * Get jobs ready to run
     *
     * @param int $limit
     * @return \Fiserv\Payments\Model\ResourceModel\PendingInquiryJob\Collection
     */
    private function getReadyJobs(int $limit)
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', PendingInquiryJob::STATUS_QUEUED);
        // Use UTC time - Magento stores timestamps in UTC
        $collection->addFieldToFilter('next_run_at', ['lteq' => gmdate('Y-m-d H:i:s')]);
        $collection->setOrder('next_run_at', 'ASC');
        $collection->setPageSize($limit);
        
        return $collection;
    }
}
