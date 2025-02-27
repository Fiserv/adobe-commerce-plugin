<?php
namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Api\FailedTransaction\FailedTransactionRepositoryInterface;
use Fiserv\Payments\Model\FailedTransactionFactory;
use Fiserv\Payments\Model\ResourceModel\FailedTransaction;

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

	/**
	 * @var SubjectReader
	 */
	protected $subjectReader;

	/**
	 * @var MultiLevelLogger
	 */
	protected $logger;

	/**
	 * @var FailedTransactionRepositoryInterface
	 */
	protected $failedTransactionRepository;

	/**
	 * @var FailedTransactionFactory
	 */
	protected $failedTransactionFactory;
	protected $failedTransactionResource;

	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 * @param MultiLevelLogger $logger
	 * @param FailedTransactionRepositoryInterface $failedTransactionRepository
	 * @param FailedTransactionFactory $failedTransactionFactory
	 * @param FailedTransaction $failedTransactionResource
	 */
	public function __construct(
		ResultInterfaceFactory $resultFactory,
		SubjectReader $subjectReader,
		MultiLevelLogger $logger,
		FailedTransactionRepositoryInterface $failedTransactionRepository,
		FailedTransactionFactory $failedTransactionFactory,
		FailedTransaction $failedTransactionResource
	) {
		parent::__construct($resultFactory);
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
		$this->failedTransactionRepository = $failedTransactionRepository;
		$this->failedTransactionFactory = $failedTransactionFactory;
		$this->failedTransactionResource = $failedTransactionResource;
	}

	public function validate(array $validationSubject): ResultInterface
	{
		$chRawResponse = $this->subjectReader->readChResponseFromResponse($validationSubject);

		$errorMessages = [];
		$errorCodes = [];

		$paths = [
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
			HttpClient::STATUS_CODE_KEY => []
		];

		// Extract order ID from the validation subject
		$order = $this->subjectReader->readPayment($validationSubject)->getOrder();
		$orderIncrementId = $order->getOrderIncrementId();

		// Verify Status Code
		if (!$this->isStatusSuccessful($chRawResponse[HttpClient::STATUS_CODE_KEY])) {
			array_push($errorMessages, "Something went wrong while processing CommerceHub transaction.");
			array_push($errorCodes, $chRawResponse[HttpClient::STATUS_CODE_KEY]);
			$this->logger->logError(2, "Transaction failure. Commerce Hub response returned with unsuccessful status", "Order ID: " . ($orderIncrementId ?? "Not found"));
			$this->logger->logError(2, "Status Code: " . $chRawResponse[HttpClient::STATUS_CODE_KEY], "Order ID: " . ($orderIncrementId ?? "Not found"));
			$this->routeToFailedTransactions($chRawResponse, $paths);

			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		$transactionId = $this->subjectReader->getValueSafely($chRawResponse, 'transactionId', $paths['transactionId']);
		$apiTraceId = $this->subjectReader->getValueSafely($chRawResponse, 'apiTraceId', $paths['apiTraceId']);
		$transactionState = $this->subjectReader->getValueSafely($chRawResponse, 'transactionState', $paths['transactionState']);
		if (!$this->isStateSuccessful($transactionState)) {
			array_push($errorMessages, "Transaction state failure: " . ($transactionState ?? "Transaction state not found"));
			array_push($errorCodes, $transactionState);
			$context = "Transaction ID: " . ($transactionId ?? "Not found") . "\n"
				. "API Trace ID: " . ($apiTraceId ?? "Not found") . "\n"
				. ", Transaction state: " . ($transactionState ?? "Not found") . "\n"
				. ", Response message: " . ($this->subjectReader->getValueSafely($chRawResponse, 'responseMessage', $paths['responseMessage']) ?? "Not found") . "\n"
				. ", Payment Source Type: " . ($this->subjectReader->getValueSafely($chRawResponse, 'sourcetype', $paths['sourceType']) ?? "Not found");
			$this->logger->logError(2, "Transaction failure. Commerce Hub response returned with unsuccessful transaction state: $context", "Order ID: " . ($orderIncrementId ?? "Not found"));
			$this->routeToFailedTransactions($chRawResponse, $paths);
			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		$this->logger->logInfo(1, "Transaction success", "Order ID: " . ($orderIncrementId ?? "Not found"));
		$this->logger->logInfo(1, "Transaction ID: " . ($transactionId ?? "Not found"), "Order ID: " . ($orderIncrementId ?? "Not found"));
		return $this->createResult(true);
	}

	/**
	 * Route the failed transaction information to the failed_transactions table.
	 *
	 * @param array $chRawResponse
	 * @param array $paths
	 * @throws \Exception If saving the failed transaction fails.
	 */
	protected function routeToFailedTransactions(array $chRawResponse, array $paths)
	{
		$transactionState = $this->subjectReader->getValueSafely($chRawResponse, 'transactionState', $paths['transactionState']);
		$transactionId = $this->subjectReader->getValueSafely($chRawResponse, 'transactionId', $paths['transactionId']);
		$apiTraceId = $this->subjectReader->getValueSafely($chRawResponse, 'apiTraceId', $paths['apiTraceId']);
		$merchantOrderId = $this->subjectReader->getValueSafely($chRawResponse, 'merchantOrderId', $paths['merchantOrderId']);
		$approvalStatus = $this->subjectReader->getValueSafely($chRawResponse, 'approvalStatus', $paths['approvalStatus']);
		$totalAmount = $this->subjectReader->getValueSafely($chRawResponse, 'total', $paths['approvedAmount']);
		$remoteIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
		$currency = $this->subjectReader->getValueSafely($chRawResponse, 'currency', $paths['currency']);
		$bankAssociationDetails = isset($paths['bankAssociationDetails']) ? $this->subjectReader->getValueSafely($chRawResponse, 'associationResponseCode', $paths['bankAssociationDetails']) : null;
		$processor = $this->subjectReader->getValueSafely($chRawResponse, 'processor', $paths['processor']);
		$host = $this->subjectReader->getValueSafely($chRawResponse, 'host', $paths['host']);
		$merchantId = $this->subjectReader->getValueSafely($chRawResponse, 'merchantId', $paths['merchantId']);
		$expirationMonth = $this->subjectReader->getValueSafely($chRawResponse, 'expirationMonth', $paths['expirationMonth']);
		$expirationYear = $this->subjectReader->getValueSafely($chRawResponse, 'expirationYear', $paths['expirationYear']);
		$last4 = $this->subjectReader->getValueSafely($chRawResponse, 'last4', $paths['last4']);
		$scheme = $this->subjectReader->getValueSafely($chRawResponse, 'scheme', $paths['scheme']);
		$networkResponseCode = $this->subjectReader->getValueSafely($chRawResponse, 'networkResponseCode', $paths['networkResponseCode']);
		$bin = $this->subjectReader->getValueSafely($chRawResponse, 'bin', $paths['bin']);

		$existingFailedTransactions = $this->failedTransactionRepository->getListByOrderIncrementId($merchantOrderId);
		if (!empty($existingFailedTransactions)) {
			$failedTransaction = current($existingFailedTransactions);
		} else {
			$failedTransaction = $this->failedTransactionFactory->create();
		}

		$failedTransaction->setOrderIncrementId($merchantOrderId);
		$failedTransaction->setTransactionState($transactionState);
		$failedTransaction->setApprovalStatus($approvalStatus);
		$failedTransaction->setTotalAmount($totalAmount);
		$failedTransaction->setRemoteIp($remoteIp); // Set remote IP
		$failedTransaction->setApiTraceId($apiTraceId); // Set API Trace ID
		$failedTransaction->setProcessor($processor);
		$failedTransaction->setHost($host);
		$failedTransaction->setMerchantId($merchantId);
		$failedTransaction->setExpirationMonth($expirationMonth);
		$failedTransaction->setExpirationYear($expirationYear);
		$failedTransaction->setLast4($last4);
		$failedTransaction->setScheme($scheme);
		$failedTransaction->setNetworkResponseCode($networkResponseCode);
		$failedTransaction->setBin($bin);
		$failedTransaction->setTransactionId($transactionId);
		$failedTransaction->setCurrency($currency); // Set currency
		$failedTransaction->setBankAssociationDetails($bankAssociationDetails); // Set bank association details

		try {
			$this->failedTransactionRepository->save($failedTransaction);
			// Correct the log statement
			$transactions = $this->failedTransactionResource->getByTxnId($failedTransaction->getTransactionId());
		} catch (\Exception $e) {
			$this->logger->logError(2, "Failed to save failed transaction: " . $e->getMessage());
			throw $e; // Re-throw the exception
		}
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
}
