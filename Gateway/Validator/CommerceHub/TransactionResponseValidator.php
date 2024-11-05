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

/**
 * Validates the status of an attempted transaction
 */
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

		if (!$this->isStatusSuccessful($chRawResponse[HttpClient::STATUS_CODE_KEY])) {
			array_push($errorMessages, "Something went wrong while processing CommerceHub transaction.");
			array_push($errorCodes, );
			$this->logger->logError(1, "Transaction failure. Commerce Hub response returned with unsuccessful status");
			$this->logger->logError(2, "Status Code: " . $chRawResponse[HttpClient::STATUS_CODE_KEY]);
			
			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		$chResponseState = $chRawResponse[HttpClient::RESPONSE_KEY]["gatewayResponse"]["transactionState"];
		if (!$this->isStateSuccessful($chResponseState)) {
			array_push($errorMessages, "Transaction state failure: " . $chResponseState);
			array_push($errorCodes, $chResponseState);
			$this->logger->logError(1, "Transaction failure. Commerce Hub response returned with unsuccessful transaction state");
			$this->logger->logError(1, "Transaction ID: " . $chRawResponse[HttpClient::RESPONSE_KEY]["gatewayResponse"]["transactionProcessingDetails"]["transactionId"]);
			$this->logger->logError(2, "Transaction state: " . $chRawResponse[HttpClient::RESPONSE_KEY]["gatewayResponse"]["transactionState"]);
			$this->logger->logError(2, "Response message: " . $chRawResponse[HttpClient::RESPONSE_KEY]["paymentReceipt"]["processorResponseDetails"]["responseMessage"]);
			$this->logger->logError(2, "Payment Source Type: " . $chRawResponse[HttpClient::RESPONSE_KEY]["source"]["sourceType"]);

			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		$this->logger->logInfo(1, "Transaction success");
		$this->logger->logInfo(1, "Transaction ID: " . $chRawResponse[HttpClient::RESPONSE_KEY]["gatewayResponse"]["transactionProcessingDetails"]["transactionId"]);
		return $this->createResult(true);
	}


	private function isStatusSuccessful($statusCode) 
	{
		return (
			in_array($statusCode, $this->successStatuses) && 
			!in_array($statusCode, $this->failureStatuses)
		);
	}

	private function isStateSuccessful($state) 
	{
		return (
			in_array($state, $this->successStates) && 
			!in_array($state, $this->failureStates)
		);
	}}
