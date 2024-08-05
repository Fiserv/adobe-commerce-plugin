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

/**
 * Validates the status of an attempted transaction
 */
abstract class TransactionResponseValidator extends AbstractValidator
{	
	const HTTP_CREATED = 201;
	const HTTP_UNAUTHORIZED = 400;
	const HTTP_NOTFOUND = 404;

	const STATE_DECLINED = "DECLINED";
	const STATE_GATEWAY_ERROR = "GATEWAY_ERROR";
	const STATE_TIMEOUT = "TIMEOUT";
	const STATE_CAPTURE = "CAPTURED";
	const STATE_AUTHORIZED = "AUTHORIZED";
	const STATE_VOIDED = "VOIDED";

	private $successStatuses = [
		self::HTTP_CREATED
	];

	private $failureStatuses = [
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
	private $subjectReader;

	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(ResultInterfaceFactory $resultFactory, SubjectReader $subjectReader)
	{
		parent::__construct($resultFactory);
		$this->subjectReader = $subjectReader;
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

			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		$chResponseState = $chRawResponse[HttpClient::RESPONSE_KEY]["gatewayResponse"]["transactionState"];
		if (!$this->isStateSuccessful($chResponseState)) {
			array_push($errorMessages, "Transaction state failure: " . $chResponseState);
			array_push($errorCodes, $chResponseState);

			return $this->createResult(false, $errorMessages, $errorCodes);
		}

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
