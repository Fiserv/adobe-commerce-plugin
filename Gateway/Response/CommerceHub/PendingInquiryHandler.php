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
use Fiserv\Payments\Helper\OrderIdHelper;
use Magento\Payment\Gateway\Response\HandlerInterface;

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
    * @var OrderIdHelper
     */
    private $orderIdHelper;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
     * @param SubjectReader $subjectReader
     * @param PendingInquiryJobRepository $jobRepository
     * @param OrderIdHelper $orderIdHelper
     * @param MultiLevelLogger $logger
     */
    public function __construct(
        SubjectReader $subjectReader,
        PendingInquiryJobRepository $jobRepository,
        OrderIdHelper $orderIdHelper,
        MultiLevelLogger $logger
    ) {
        $this->subjectReader = $subjectReader;
        $this->jobRepository = $jobRepository;
        $this->orderIdHelper = $orderIdHelper;
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
        try {
            $paymentDO = $this->subjectReader->readPayment($handlingSubject);
            $payment = $paymentDO->getPayment();
            
            $orderIncrementId = $this->resolveOrderIncrementId($paymentDO, $payment);
            if (!$orderIncrementId) {
                $this->logger->logError(2, 'PendingInquiryHandler: missing order increment id, cannot create inquiry job');
                return;
            }

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
            if (!$isAsync || empty($referenceOrderId)) {
                return;
            }

            $this->logger->logInfo(1, 'Order scheduled for async inquiry processing', $logIdentifier);

            $paymentMethod = $payment->getMethod();
            $orderId = $this->orderIdHelper->resolveOrderId($paymentDO, $payment);

            $this->createInquiryJob($orderId, $orderIncrementId, $referenceOrderId, $paymentMethod, $logIdentifier);
        } catch (\Throwable $e) {
            $this->logger->logError(2, "Failed to handle inquiry: " . $e->getMessage(), $orderIncrementId ?? 'unknown');
        }
    }

    /**
     * Resolve order increment ID from payment or adapter order
     *
     * @param \Magento\Payment\Gateway\Data\PaymentDataObjectInterface $paymentDO
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $payment
     * @return string|null
     */
    private function resolveOrderIncrementId($paymentDO, $payment): ?string
    {
        $paymentOrder = $payment->getOrder();
        $incrementId = $this->safeCall($paymentOrder, 'getIncrementId');
        
        if (!$incrementId) {
            $adapterOrder = $paymentDO->getOrder();
            $incrementId = $this->safeCall($adapterOrder, 'getOrderIncrementId');
        }

        return $incrementId;
    }

    /**
     * Create inquiry job with error handling
     *
     * @param int|null $orderId
     * @param string $orderIncrementId
     * @param string $referenceOrderId
     * @param string $paymentMethod
     * @param string $logIdentifier
     * @return void
     */
    private function createInquiryJob(?int $orderId, string $orderIncrementId, string $referenceOrderId, string $paymentMethod, string $logIdentifier): void
    {
        try {
            $this->jobRepository->createJob(
                $orderId,
                $orderIncrementId,
                $referenceOrderId,
                $paymentMethod,
                self::FIRST_RETRY_SECONDS
            );
        } catch (\Throwable $e) {
            $this->logger->logError(
                2,
                sprintf("Failed to create inquiry job: %s in %s:%d", $e->getMessage(), $e->getFile(), $e->getLine()),
                $logIdentifier
            );
        }
    }

    /**
     * Safely call a method on an object, catching TypeError and other exceptions
     *
     * @param mixed $object
     * @param string $method
     * @return mixed|null
     */
    private function safeCall($object, string $method)
    {
        if (!is_object($object) || !method_exists($object, $method)) {
            return null;
        }

        try {
            return $object->$method();
        } catch (\Throwable $e) {
            return null;
        }
    }

}
