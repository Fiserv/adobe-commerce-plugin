<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;

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

	protected $successStates = [
	];

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
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 * @param MultiLevelLogger $logger
	 */
	public function __construct(
		ResultInterfaceFactory $resultFactory,
		SubjectReader $subjectReader,
		MultiLevelLogger $logger
	) {
		parent::__construct($resultFactory);
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
	}

	/**
	 * @inheritdoc
	 */
	public function validate(array $validationSubject): ResultInterface
	{
		$chRawResponse = $this->subjectReader->readChResponseFromResponse($validationSubject);

		$errorMessages = [];
		$errorCodes = [];

		// Define the paths for values to be extracted
		$paths = [
			'transactionId' => [HttpClient::RESPONSE_KEY, 'gatewayResponse', 'transactionProcessingDetails'],
			'responseMessage' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
			'sourceType' => [HttpClient::RESPONSE_KEY, 'source'],
			'transactionState' => [HttpClient::RESPONSE_KEY, 'gatewayResponse'],
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

			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		// Check transaction state
		$transactionId = $this->subjectReader->getValueSafely($chRawResponse, 'transactionId', $paths['transactionId']);
		$transactionState = $this->subjectReader->getValueSafely($chRawResponse, 'transactionState', $paths['transactionState']);
		if (!$this->isStateSuccessful($transactionState)) {
			array_push($errorMessages, "Transaction state failure: " . ($transactionState ?? "Transaction state not found"));
			array_push($errorCodes, $transactionState);
			// Log transaction context
			$context = "Transaction ID: " . ($transactionId ?? "Not found") . "\n"
				. ", Transaction state: " . ($transactionState ?? "Not found") . "\n"
				. ", Response message: " . ($this->subjectReader->getValueSafely($chRawResponse, 'responseMessage', $paths['responseMessage']) ?? "Not found") . "\n"
				. ", Payment Source Type: " . ($this->subjectReader->getValueSafely($chRawResponse, 'sourceType', $paths['sourceType']) ?? "Not found");
			$this->logger->logError(2, "Transaction failure. Commerce Hub response returned with unsuccessful transaction state: $context", "Order ID: " . ($orderIncrementId ?? "Not found"));
			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		$this->logger->logInfo(1, "Transaction success", "Order ID: " . ($orderIncrementId ?? "Not found"));
		$this->logger->logInfo(1, "Transaction ID: " . ($transactionId ?? "Not found"), "Order ID: " . ($orderIncrementId ?? "Not found"));

		return $this->createResult(true);
	}

	/**
	 * Check if the status code is considered successful
	 *
	 * @param int $statusCode
	 * @return bool
	 */
	private function isStatusSuccessful($statusCode)
	{
		return (
			in_array($statusCode, $this->successStatuses) &&
			!in_array($statusCode, $this->failureStatuses)
		);
	}

	/**
	 * Check if the transaction state is considered successful
	 *
	 * @param string $state
	 * @return bool
	 */
	private function isStateSuccessful($state)
	{
		return (
			in_array($state, $this->successStates) &&
			!in_array($state, $this->failureStates)
		);
	}
}
