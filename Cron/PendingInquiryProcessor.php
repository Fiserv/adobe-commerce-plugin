<?php
/**
 * Pending Inquiry Processor for Affirm
 * Cron job that processes queued inquiry jobs and updates orders based on transaction results
 * Runs periodically to check async transaction status with CommerceHub
 */
namespace Fiserv\Payments\Cron;

use Fiserv\Payments\Model\PendingInquiryJob;
use Fiserv\Payments\Model\PendingInquiryJobRepository;
use Fiserv\Payments\Model\Adapter\CommerceHub\OrderInquiryAdapter;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class PendingInquiryProcessor
{
    /**
     * @var PendingInquiryJobRepository
     */
    private $jobRepository;

    /**
     * @var OrderInquiryAdapter
     */
    private $inquiryService;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @param PendingInquiryJobRepository $jobRepository
     * @param OrderInquiryAdapter $inquiryService
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param MultiLevelLogger $logger
     */
    public function __construct(
        PendingInquiryJobRepository $jobRepository,
        OrderInquiryAdapter $inquiryService,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        InvoiceRepositoryInterface $invoiceRepository,
        MultiLevelLogger $logger
    ) {
        $this->jobRepository = $jobRepository;
        $this->inquiryService = $inquiryService;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->invoiceRepository = $invoiceRepository;
        $this->logger = $logger;
    }
    /**
     * Execute the cron job
     *
     * @return void
     */
    public function execute(): void
    {
        $jobs = $this->jobRepository->getReadyJobs(50);

        foreach ($jobs as $job) {
            try {
                $this->processJob($job);
            } catch (\Exception $e) {
                // Log silently
            }
        }

        // Cleanup old jobs (older than 30 days)
        $this->jobRepository->cleanupOldJobs(30);
    }

    /**
     * Process a single inquiry job
     *
     * @param PendingInquiryJob $job
     * @return bool True if job completed successfully
     */
    public function processJob(PendingInquiryJob $job): bool
    {
        $logIdentifier = "Order ID: " . $job->getOrderIncrementId();

        // Mark as processing to prevent duplicate processing
        $job->setStatus(PendingInquiryJob::STATUS_PROCESSING);
        $this->jobRepository->save($job);

        try {
            // Execute the inquiry
            $result = $this->inquiryService->executeInquiry(
                $job->getReferenceOrderId(),
                $job->getOrderIncrementId(),
                $job->isOrderType()
            );

            // Store the response
            $job->setLastResponse(json_encode($result['rawResponse']));

            // Update transaction state and approval status
            if (isset($result['transactionState'])) {
                $job->setTransactionState($result['transactionState']);
            }
            if (isset($result['approvalStatus'])) {
                $job->setApprovalStatus($result['approvalStatus']);
            }

            // Check if we got a final status
            if ($result['success'] && !$this->inquiryService->shouldRetry($result['transactionState'])) {
                // Transaction has reached a final state
                $this->handleFinalStatus($job, $result);
                return true;
            }

            // Transaction is still processing - schedule retry if attempts remain
            $job->incrementAttempts();

            if ($job->hasReachedMaxAttempts()) {
                // Void the authorization in CommerceHub before marking as failed
                try {
                    $this->inquiryService->voidTransaction(
                        $job->getReferenceOrderId(),
                        $job->getOrderIncrementId(),
                        $job->isOrderType()
                    );
                    $this->logger->logInfo(1, "Transaction voided in CommerceHub due to max attempts reached", $logIdentifier);
                } catch (\Exception $voidError) {
                    $this->logger->logError(2, "Failed to void transaction in CommerceHub: " . $voidError->getMessage(), $logIdentifier);
                }
                
                $job->markFailed("Max attempts reached without final transaction status");
                $this->jobRepository->save($job);
                return false;
            }

            // Re-queue for next attempt
            $job->setStatus(PendingInquiryJob::STATUS_QUEUED);
            $this->jobRepository->save($job);

            return false;

        } catch (\Exception $e) {
            $this->logger->logError(2, "Inquiry execution failed: " . $e->getMessage(), $logIdentifier);

            $job->incrementAttempts();
            $job->setErrorMessage($e->getMessage());

            if ($job->hasReachedMaxAttempts()) {
                // Void the authorization in CommerceHub before marking as failed
                try {
                    $this->inquiryService->voidTransaction(
                        $job->getReferenceOrderId(),
                        $job->getOrderIncrementId(),
                        $job->isOrderType()
                    );
                    $this->logger->logInfo(1, "Transaction voided in CommerceHub due to max attempts reached", $logIdentifier);
                } catch (\Exception $voidError) {
                    $this->logger->logError(2, "Failed to void transaction in CommerceHub: " . $voidError->getMessage(), $logIdentifier);
                }
                
                $job->markFailed("Inquiry failed after max attempts: " . $e->getMessage());
            } else {
                $job->setStatus(PendingInquiryJob::STATUS_QUEUED);
            }

            $this->jobRepository->save($job);
            return false;
        }
    }

    /**
     * Handle final transaction status and update order
     *
     * @param PendingInquiryJob $job
     * @param array $result
     * @return void
     */
    private function handleFinalStatus(PendingInquiryJob $job, array $result): void
    {
        $logIdentifier = "Order ID: " . $job->getOrderIncrementId();

        try {
            // Load the order (try resolving by increment id if order_id is not set)
            $order = null;
            $orderId = $job->getOrderId();
            if ($orderId) {
                $order = $this->orderRepository->get($orderId);
            } else {
                // Try to load by increment id using repository
                $searchCriteria = $this->searchCriteriaBuilder
                    ->addFilter('increment_id', $job->getOrderIncrementId())
                    ->create();
                $orders = $this->orderRepository->getList($searchCriteria)->getItems();
                if (!empty($orders)) {
                    $order = reset($orders);
                    // Update job's order_id for future processing
                    $job->setOrderId($order->getId());
                } else {
                    throw new \Exception('Order not found for increment id: ' . $job->getOrderIncrementId());
                }
            }

            // Update order based on transaction result
            if ($this->inquiryService->isTransactionSuccessful($result)) {
                $this->handleSuccessfulTransaction($order, $job, $result);
            } elseif ($this->inquiryService->isTransactionFailed($result)) {
                $this->handleFailedTransaction($order, $job, $result);
            }

            // Mark job as completed
            $job->markCompleted($result['transactionState'], $result['approvalStatus']);
            $this->jobRepository->save($job);

        } catch (\Exception $e) {
            $this->logger->logError(2, "Failed to update order: " . $e->getMessage(), $logIdentifier);
            $job->setErrorMessage("Order update failed: " . $e->getMessage());
            $job->setStatus(PendingInquiryJob::STATUS_QUEUED);
            $this->jobRepository->save($job);
        }
    }

    /**
     * Handle successful transaction - add confirmation comment to order
     *
     * @param Order $order
     * @param PendingInquiryJob $job
     * @param array $result
     * @return void
     */
    private function handleSuccessfulTransaction(Order $order, PendingInquiryJob $job, array $result): void
    {
        $logIdentifier = "Order ID: " . $job->getOrderIncrementId();

        $transactionState = $result['transactionState'];
        $transactionId = $result['transactionId'] ?? null;

        // Store transaction state in payment additional info and clear pending flag
        $payment = $order->getPayment();
        if ($payment) {
            $payment->setIsTransactionPending(false);
            $payment->setAdditionalInformation('async_transaction', false);
            $payment->setAdditionalInformation('inquiry_transaction_state', $transactionState);
            $payment->setAdditionalInformation('inquiry_transaction_id', $transactionId);
            if ($transactionId) {
                $payment->setTransactionId($transactionId);
                $payment->setLastTransId($transactionId);
            }
        }

        // Reload order to get fresh state
        $orderId = $order->getId();
        $workingOrder = $this->orderRepository->get($orderId);

        // Move order to processing if it was pending/payment review
        if (in_array($workingOrder->getState(), [Order::STATE_PENDING_PAYMENT, Order::STATE_PAYMENT_REVIEW, Order::STATE_NEW], true)) {
            $workingOrder->setState(Order::STATE_PROCESSING);
            $workingOrder->setStatus($workingOrder->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
            $this->orderRepository->save($workingOrder);
        }
        // Capture the existing invoice that was created with the order - using Magento's invoice repository
        try {
            if ($workingOrder->hasInvoices()) {
                foreach ($workingOrder->getInvoiceCollection() as $invoice) {
                    if ($invoice->getState() == \Magento\Sales\Model\Order\Invoice::STATE_OPEN) {
                        if ($transactionId) {
                            $invoice->setTransactionId($transactionId);
                        }
                        $invoice->pay();
                        $this->invoiceRepository->save($invoice);
                        $amountText = $workingOrder->getBaseCurrency()->formatTxt($invoice->getBaseGrandTotal());
                        $comment = sprintf(
                            'Captured amount of %s online. Transaction ID: "%s"',
                            $amountText,
                            $transactionId ?? 'N/A'
                        );
                        $workingOrder->addCommentToStatusHistory($comment, false, true);
                        $this->orderRepository->save($workingOrder);
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->logError(2, "Failed to capture invoice: " . $e->getMessage(), $logIdentifier);
        }
    }

    /**
     * Handle failed/declined transaction - cancel order
     *
     * @param Order $order
     * @param PendingInquiryJob $job
     * @param array $result
     * @return void
     */
    private function handleFailedTransaction(Order $order, PendingInquiryJob $job, array $result): void
    {
        $logIdentifier = "Order ID: " . $job->getOrderIncrementId();

        // Only cancel if order is still in pending state
        if ($order->canCancel()) {
            try {
                $order->cancel();
                $order->addCommentToStatusHistory(
                    sprintf(
                        'Order cancelled - Payment %s via async inquiry. State: %s, Approval: %s',
                        $result['transactionState'] === OrderInquiryAdapter::STATE_DECLINED ? 'declined' : 'voided',
                        $result['transactionState'],
                        $result['approvalStatus'] ?? 'N/A'
                    ),
                    true,
                    true
                );
                $this->orderRepository->save($order);

                $this->logger->logInfo(1, "Order cancelled due to declined payment", $logIdentifier);

            } catch (\Exception $e) {
                // Log silently
            }
        }
    }

    /**
     * Manually trigger processing of a specific job (for testing/fallback)
     *
     * @param string $orderIncrementId
     * @return array Result array with status info
     */
    public function processJobByOrderId(string $orderIncrementId): array
    {
        $job = $this->jobRepository->getByOrderIncrementId($orderIncrementId);

        if (!$job) {
            return [
                'success' => false,
                'message' => 'No inquiry job found for order: ' . $orderIncrementId
            ];
        }

        if ($job->getStatus() === PendingInquiryJob::STATUS_COMPLETED) {
            return [
                'success' => true,
                'message' => 'Job already completed',
                'transactionState' => $job->getTransactionState(),
                'approvalStatus' => $job->getApprovalStatus()
            ];
        }

        $result = $this->processJob($job);

        return [
            'success' => $result,
            'message' => $result ? 'Job processed successfully' : 'Job queued for retry',
            'transactionState' => $job->getTransactionState(),
            'approvalStatus' => $job->getApprovalStatus(),
            'nextRunAt' => $job->getStatus() === PendingInquiryJob::STATUS_QUEUED ? $job->getNextRunAt() : null
        ];
    }
}
