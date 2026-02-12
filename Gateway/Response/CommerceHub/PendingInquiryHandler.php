<?php
/**
 * Pending Inquiry Handler for Affirm
 * Queues inquiry jobs when transaction returns PROCESSING/202 response
 * Job will poll CommerceHub later to get final transaction status
 */
namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Model\PendingInquiryJobRepository;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class PendingInquiryHandler implements HandlerInterface
{
    const STATE_PROCESSING = 'PROCESSING';
    const HTTP_ACCEPTED = 202;
    const FIRST_RETRY_SECONDS = 60; // 1 minute

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var PendingInquiryJobRepository
     */
    private $jobRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
     * @param SubjectReader $subjectReader
     * @param PendingInquiryJobRepository $jobRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param MultiLevelLogger $logger
     */
    public function __construct(
        SubjectReader $subjectReader,
        PendingInquiryJobRepository $jobRepository,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        MultiLevelLogger $logger
    ) {
        $this->subjectReader = $subjectReader;
        $this->jobRepository = $jobRepository;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * Queue inquiry job for async transaction
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();
        $orderIncrementId = $order->getOrderIncrementId();
        $logIdentifier = 'Order ID: ' . $orderIncrementId;

        $chResponse = $this->subjectReader->readChResponse($response);
        $statusCode = $chResponse[HttpClient::STATUS_CODE_KEY] ?? 200;
        $responseBody = $chResponse[HttpClient::RESPONSE_KEY] ?? [];
        
        $gatewayResponse = $responseBody['gatewayResponse'] ?? [];
        $transactionState = $gatewayResponse['transactionState'] ?? null;
        $transactionProcessingDetails = $gatewayResponse['transactionProcessingDetails'] ?? [];
        $referenceOrderId = $transactionProcessingDetails['orderId'] ?? null;

        // Check if this is an async PROCESSING response
        $isAsync = ($statusCode == self::HTTP_ACCEPTED) || ($transactionState === self::STATE_PROCESSING);
        if (!$isAsync) {
            return;
        }

        if (empty($referenceOrderId)) {
            return;
        }

        $this->logger->logInfo(1, 'Order scheduled for async inquiry processing', $logIdentifier);

        try {
            $paymentMethod = $payment->getMethod();
            $orderId = $order->getId();
            
            // Try to resolve order ID if not yet available
            if (empty($orderId)) {
                try {
                    $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilter('increment_id', $orderIncrementId)
                        ->create();
                    $orders = $this->orderRepository->getList($searchCriteria)->getItems();
                    if (!empty($orders)) {
                        $orderModel = reset($orders);
                        $orderId = $orderModel->getId();
                    }
                } catch (\Exception $e) {
                    // Order not saved yet
                }
            }

            // Create inquiry job - first run in 60 seconds
            $job = $this->jobRepository->createJob(
                $orderId ? (int)$orderId : null,
                $orderIncrementId,
                $referenceOrderId,
                $paymentMethod,
                self::FIRST_RETRY_SECONDS
            );

        } catch (\Exception $e) {
            $this->logger->logError(2, "Failed to create inquiry job: " . $e->getMessage(), $logIdentifier);
            // Don't throw - we don't want to fail the order, just log the error
        }
    }

}
