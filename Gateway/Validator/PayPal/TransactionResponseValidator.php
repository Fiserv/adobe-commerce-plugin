<?php
namespace Fiserv\Payments\Gateway\Validator\PayPal;

use Fiserv\Payments\Gateway\Subject\PayPal\SubjectReader;
use Fiserv\Payments\Gateway\Http\PayPal\Client\HttpClient;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Service\PayPal\FailedTransactionManager;

abstract class TransactionResponseValidator extends AbstractValidator
{
    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_UNAUTHORIZED = 400;
    const HTTP_NOTFOUND = 404;

    const STATE_DECLINED = "DECLINED";
    const STATE_GATEWAY_ERROR = "GATEWAY_ERROR";
    const STATE_TIMEOUT = "TIMEOUT";
    const STATE_CAPTURE = "CAPTURED";
    const STATE_AUTHORIZED = "AUTHORIZED";
    const STATE_VOIDED = "VOIDED";

    protected $successStatuses = [
        self::HTTP_CREATED,
        self::HTTP_OK
    ];

    protected $failureStatuses = [
        self::HTTP_UNAUTHORIZED,
        self::HTTP_NOTFOUND,
    ];

    protected $successStates = [];

    protected $failureStates = [
        self::STATE_DECLINED,
        self::STATE_GATEWAY_ERROR,
        self::STATE_TIMEOUT,
    ];

    protected $paths = [
        'transactionId' => ['gatewayResponse', 'transactionProcessingDetails'],
        'apiTraceId' => ['gatewayResponse', 'transactionProcessingDetails'],
        'responseMessage' => ['paymentReceipt', 'processorResponseDetails'],
        'sourceType' => ['source'],
        'merchantOrderId' => ['transactionDetails'],
        'transactionState' => ['gatewayResponse'],
        'approvalStatus' => ['paymentReceipt', 'processorResponseDetails'],
        'approvedAmount' => ['paymentReceipt', 'approvedAmount'],
        'processor' => ['paymentReceipt', 'processorResponseDetails'],
        'host' => ['paymentReceipt', 'processorResponseDetails'],
        'merchantId' => ['merchantDetails'],
        'expirationMonth' => ['source', 'card'],
        'expirationYear' => ['source', 'card'],
        'last4' => ['source', 'card'],
        'scheme' => ['source', 'card'],
        'bin' => ['source', 'card'],
        'networkResponseCode' => ['networkDetails'],
        'currency' => ['paymentReceipt', 'approvedAmount'],
        'bankAssociationDetails' => ['paymentReceipt', 'processorResponseDetails', 'bankAssociationDetails'],
        'securityCodeMatch' => ['paymentReceipt', 'processorResponseDetails', 'bankAssociationDetails', 'avsSecurityCodeResponse'],
        'errorCode' => ['error', 0],
        'errorMessage' => ['error', 0],
        'hostResponseMessage' => ['paymentReceipt', 'processorResponseDetails'],
        'retrievalReferenceNumber' => ['transactionDetails'],
        'countryCode' => [],
        'responseCode' => ['paymentReceipt', 'processorResponseDetails'],
        'merchantAdviceCode' => ['networkDetails'],
        'amount' => [],
        HttpClient::STATUS_CODE_KEY => []
    ];

    /**
     * @var SubjectReader
     */
    protected $subjectReader;

    /**
     * @var MultiLevelLogger
     */
    protected $logger;

    protected $failedTransactionManager;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param SubjectReader $subjectReader
     * @param MultiLevelLogger $logger
     * @param FailedTransactionManager $failedTransactionManager
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        SubjectReader $subjectReader,
        MultiLevelLogger $logger,
        FailedTransactionManager $failedTransactionManager
    ) {
        parent::__construct($resultFactory);
        $this->subjectReader = $subjectReader;
        $this->logger = $logger;
        $this->failedTransactionManager = $failedTransactionManager;
    }

    public function validate(array $validationSubject): ResultInterface
    {
        $paypalResponse = $this->subjectReader->readPayPalResponse($validationSubject);

        $errorMessages = [];
        $errorCodes = [];

        // Verify Status Code
        $statusCode = $this->subjectReader->getValueSafely($paypalResponse, HttpClient::STATUS_CODE_KEY, $this->paths[HttpClient::STATUS_CODE_KEY]);
        if (!$this->isStatusSuccessful($statusCode)) {
            array_push($errorMessages, "Something went wrong while processing PayPal transaction.");
            array_push($errorCodes, $statusCode);
            $this->logger->logError(2, "Transaction failure. PayPal response returned with unsuccessful status", "Status Code: " . $statusCode);
            return $this->createResult(false, $errorMessages, $errorCodes);
        }

        $transactionId = $this->subjectReader->getValueSafely($paypalResponse, 'transactionId', $this->paths['transactionId']);
        $apiTraceId = $this->subjectReader->getValueSafely($paypalResponse, 'apiTraceId', $this->paths['apiTraceId']);
        $transactionState = $this->subjectReader->getValueSafely($paypalResponse, 'transactionState', $this->paths['transactionState']);
        
        if (!$this->isStateSuccessful($transactionState)) {
            array_push($errorMessages, "Transaction state failure: " . ($transactionState ?? "Transaction state not found"));
            array_push($errorCodes, $transactionState);
            $context = "Transaction ID: " . ($transactionId ?? "Not found") . "\n"
                . "API Trace ID: " . ($apiTraceId ?? "Not found") . "\n"
                . ", Transaction state: " . ($transactionState ?? "Not found") . "\n"
                . ", Response message: " . ($this->subjectReader->getValueSafely($paypalResponse, 'responseMessage', $this->paths['responseMessage']) ?? "Not found") . "\n"
                . ", Payment Source Type: " . ($this->subjectReader->getValueSafely($paypalResponse, 'sourceType', $this->paths['sourceType']) ?? "Not found");
            $this->logger->logError(2, "Transaction failure. PayPal response returned with unsuccessful transaction state: $context");
            return $this->createResult(false, $errorMessages, $errorCodes);
        }

        $this->logger->logInfo(1, "Transaction success", "Transaction ID: " . ($transactionId ?? "Not found"));
        return $this->createResult(true);
    }

    protected function isStatusSuccessful($statusCode)
    {
        return (
            in_array($statusCode, $this->successStatuses) &&
            !in_array($statusCode, $this->failureStatuses)
        );
    }

    protected function isStateSuccessful($state)
    {
        return (
            in_array($state, $this->successStates) &&
            !in_array($state, $this->failureStates)
        );
    }

    abstract protected function getPaymentAction();
}
