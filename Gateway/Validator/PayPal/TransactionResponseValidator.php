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
		'transactionId' => [HttpClient::RESPONSE_KEY, 'gatewayResponse', 'transactionProcessingDetails'],
		'apiTraceId' => [HttpClient::RESPONSE_KEY, 'gatewayResponse', 'transactionProcessingDetails'],
		'responseMessage' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
		'sourceType' => [HttpClient::RESPONSE_KEY, 'source'],
		'merchantOrderId' => [HttpClient::RESPONSE_KEY, 'transactionDetails'],
		'transactionState' => [HttpClient::RESPONSE_KEY, 'gatewayResponse'],
		'approvalStatus' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
		'approvedAmount' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'approvedAmount'],
		'processor' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
		'host' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
		'merchantId' => [HttpClient::RESPONSE_KEY, 'merchantDetails'],
		'expirationMonth' => [HttpClient::RESPONSE_KEY, 'source', 'card'],
		'expirationYear' => [HttpClient::RESPONSE_KEY, 'source', 'card'],
		'last4' => [HttpClient::RESPONSE_KEY, 'source', 'card'],
		'scheme' => [HttpClient::RESPONSE_KEY, 'source', 'card'],
		'bin' => [HttpClient::RESPONSE_KEY, 'source', 'card'],
		'networkResponseCode' => [HttpClient::RESPONSE_KEY, 'networkDetails'],
		'currency' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'approvedAmount'],
		'bankAssociationDetails' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails', 'bankAssociationDetails'],
		'securityCodeMatch' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails', 'bankAssociationDetails', 'avsSecurityCodeResponse'],
		'errorCode' => [HttpClient::RESPONSE_KEY, 'error', 0],
		'errorMessage' => [HttpClient::RESPONSE_KEY, 'error', 0],
		'hostResponseMessage' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
		'retrievalReferenceNumber' => [HttpClient::RESPONSE_KEY, 'transactionDetails'],
		'countryCode' => [HttpClient::RESPONSE_KEY],
		'responseCode' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
		'merchantAdviceCode' => [HttpClient::RESPONSE_KEY, 'networkDetails'],
		'amount' => [HttpClient::RESPONSE_KEY],
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
		$paypalRawResponse = $this->subjectReader->readPayPalResponseFromResponse($validationSubject);

		$errorMessages = [];
		$errorCodes = [];

		// Extract order ID & country Code from the validation subject
		$order = $this->subjectReader->readPayment($validationSubject)->getOrder();
		$orderIncrementId = $order->getOrderIncrementId();
		$countryCode = $order->getBillingAddress()->getCountryId();

		// Verify Status Code
		if (!$this->isStatusSuccessful($paypalRawResponse[HttpClient::STATUS_CODE_KEY])) {
			array_push($errorMessages, "Something went wrong while processing PayPal transaction.");
			array_push($errorCodes, $paypalRawResponse[HttpClient::STATUS_CODE_KEY]);
			$paypalRawResponse[HttpClient::RESPONSE_KEY]["amount"] = $validationSubject["amount"];
			$this->logger->logError(2, "Transaction failure. PayPal response returned with unsuccessful status", "Order ID: " . ($orderIncrementId ?? "Not found"));
			$this->logger->logError(2, "Status Code: " . $paypalRawResponse[HttpClient::STATUS_CODE_KEY], "Order ID: " . ($orderIncrementId ?? "Not found"));
			$amount = $this->subjectReader->readAmount($validationSubject);
			$paypalRawResponse[HttpClient::RESPONSE_KEY]['countryCode'] = $countryCode;
			$this->failedTransactionManager->createFailedTransaction($orderIncrementId, $paypalRawResponse, $this->paths, $this->getPaymentAction());
			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		$transactionId = $this->subjectReader->getValueSafely($paypalRawResponse, 'transactionId', $this->paths['transactionId']);
		$apiTraceId = $this->subjectReader->getValueSafely($paypalRawResponse, 'apiTraceId', $this->paths['apiTraceId']);
		$transactionState = $this->subjectReader->getValueSafely($paypalRawResponse, 'transactionState', $this->paths['transactionState']);
		if (!$this->isStateSuccessful($transactionState)) {
			array_push($errorMessages, "Transaction state failure: " . ($transactionState ?? "Transaction state not found"));
			array_push($errorCodes, $transactionState);
			$paypalRawResponse[HttpClient::RESPONSE_KEY]["amount"] = $validationSubject["amount"];
			$context = "Transaction ID: " . ($transactionId ?? "Not found") . "\n"
				. "API Trace ID: " . ($apiTraceId ?? "Not found") . "\n"
				. ", Transaction state: " . ($transactionState ?? "Not found") . "\n"
				. ", Response message: " . ($this->subjectReader->getValueSafely($paypalRawResponse, 'responseMessage', $this->paths['responseMessage']) ?? "Not found") . "\n"
				. ", Payment Source Type: " . ($this->subjectReader->getValueSafely($paypalRawResponse, 'sourcetype', $this->paths['sourceType']) ?? "Not found");
			$this->logger->logError(2, "Transaction failure. PayPal response returned with unsuccessful transaction state: $context", "Order ID: " . ($orderIncrementId ?? "Not found"));
			$amount = $this->subjectReader->readAmount($validationSubject);
			$paypalRawResponse[HttpClient::RESPONSE_KEY]['countryCode'] = $countryCode;
		
			$this->failedTransactionManager->createFailedTransaction($orderIncrementId, $paypalRawResponse, $this->paths, $this->getPaymentAction());
			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		$this->logger->logInfo(1, "Transaction success", "Order ID: " . ($orderIncrementId ?? "Not found"));
		$this->logger->logInfo(1, "Transaction ID: " . ($transactionId ?? "Not found"), "Order ID: " . ($orderIncrementId ?? "Not found"));
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
