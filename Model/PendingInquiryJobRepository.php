<?php
/**
 * Repository for Pending Inquiry Job
 */
namespace Fiserv\Payments\Model;

use Fiserv\Payments\Model\PendingInquiryJob;
use Fiserv\Payments\Model\PendingInquiryJobFactory;
use Fiserv\Payments\Model\ResourceModel\PendingInquiryJob as PendingInquiryJobResource;
use Fiserv\Payments\Model\ResourceModel\PendingInquiryJob\CollectionFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Fiserv\Payments\Logger\MultiLevelLogger;

class PendingInquiryJobRepository
{
    /**
     * @var PendingInquiryJobResource
     */
    private $resource;

    /**
     * @var PendingInquiryJobFactory
     */
    private $pendingInquiryJobFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
     * @param PendingInquiryJobResource $resource
     * @param PendingInquiryJobFactory $pendingInquiryJobFactory
     * @param CollectionFactory $collectionFactory
     * @param MultiLevelLogger $logger
     */
    public function __construct(
        PendingInquiryJobResource $resource,
        PendingInquiryJobFactory $pendingInquiryJobFactory,
        CollectionFactory $collectionFactory,
        MultiLevelLogger $logger
    ) {
        $this->resource = $resource;
        $this->pendingInquiryJobFactory = $pendingInquiryJobFactory;
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
    }

    /**
     * Save pending inquiry job
     *
     * @param PendingInquiryJob $job
     * @return PendingInquiryJob
     * @throws CouldNotSaveException
     */
    public function save(PendingInquiryJob $job): PendingInquiryJob
    {
        try {
            $this->resource->save($job);
        } catch (\Exception $e) {
            $this->logger->logError(2, "Could not save pending inquiry job: " . $e->getMessage());
            throw new CouldNotSaveException(__('Could not save the inquiry job: %1', $e->getMessage()));
        }
        return $job;
    }

    /**
     * Get job by ID
     *
     * @param int $entityId
     * @return PendingInquiryJob
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): PendingInquiryJob
    {
        $job = $this->pendingInquiryJobFactory->create();
        $this->resource->load($job, $entityId);
        if (!$job->getEntityId()) {
            throw new NoSuchEntityException(__('Inquiry job with id "%1" does not exist.', $entityId));
        }
        return $job;
    }

    /**
     * Get job by order increment ID
     *
     * @param string $orderIncrementId
     * @return PendingInquiryJob|null
     */
    public function getByOrderIncrementId(string $orderIncrementId): ?PendingInquiryJob
    {
        $job = $this->pendingInquiryJobFactory->create();
        $this->resource->load($job, $orderIncrementId, 'order_increment_id');
        if (!$job->getEntityId()) {
            return null;
        }
        return $job;
    }

    /**
     * Delete job
     *
     * @param PendingInquiryJob $job
     * @return bool
     */
    public function delete(PendingInquiryJob $job): bool
    {
        try {
            $this->resource->delete($job);
        } catch (\Exception $e) {
            $this->logger->logError(2, "Could not delete pending inquiry job: " . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Create a new inquiry job for an order
     *
     * @param int $orderId
     * @param string $orderIncrementId
     * @param string $referenceOrderId
     * @param string $paymentMethod
     * @param int $firstIntervalSeconds
     * @return PendingInquiryJob
     * @throws CouldNotSaveException
     */
    public function createJob(
        ?int $orderId,
        string $orderIncrementId,
        string $referenceOrderId,
        string $paymentMethod,
        int $firstIntervalSeconds = 60
    ): PendingInquiryJob {
        // Check if job already exists
        if ($orderId !== null && $this->resource->jobExistsForOrder($orderId)) {
            $this->logger->logInfo(1, "Inquiry job already exists for order: " . $orderIncrementId);
            return $this->getByOrderIncrementId($orderIncrementId);
        }

        $job = $this->pendingInquiryJobFactory->create();
        if ($orderId !== null) {
            $job->setOrderId($orderId);
        }
        $job->setOrderIncrementId($orderIncrementId);
        $job->setReferenceOrderId($referenceOrderId);
        $job->setPaymentMethod($paymentMethod);
        $job->setStatus(PendingInquiryJob::STATUS_QUEUED);
        $job->setAttempts(0);
        // Use UTC time for consistency with Magento's database storage
        $job->setNextRunAt(gmdate('Y-m-d H:i:s', time() + $firstIntervalSeconds));

        $this->logger->logInfo(1, sprintf(
            "Creating inquiry job for order %s with reference %s, first run in %d seconds",
            $orderIncrementId,
            $referenceOrderId,
            $firstIntervalSeconds
        ));

        return $this->save($job);
    }

    /**
     * Get all jobs ready to be processed
     *
     * @param int $limit
     * @return \Fiserv\Payments\Model\ResourceModel\PendingInquiryJob\Collection
     */
    public function getReadyJobs(int $limit = 50)
    {
        $collection = $this->collectionFactory->create();
        $collection->addReadyToRunFilter()
            ->orderByNextRun()
            ->setPageSize($limit);

        return $collection;
    }

    /**
     * Get jobs by status
     *
     * @param string|array $status
     * @return \Fiserv\Payments\Model\ResourceModel\PendingInquiryJob\Collection
     */
    public function getJobsByStatus($status)
    {
        $collection = $this->collectionFactory->create();
        $collection->addStatusFilter($status);
        return $collection;
    }

    /**
     * Clean up old completed/failed jobs
     *
     * @param int $days
     * @return int Number of deleted jobs
     */
    public function cleanupOldJobs(int $days = 30): int
    {
        $deleted = $this->resource->cleanupOldJobs($days);
        if ($deleted > 0) {
            $this->logger->logInfo(1, "Cleaned up {$deleted} old inquiry jobs");
        }
        return $deleted;
    }
}
