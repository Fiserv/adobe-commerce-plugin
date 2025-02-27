<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Validator\CommerceHub\TransactionResponseValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Fiserv\Payments\Logger\MultiLevelLogger;

class AuthorizeResponseValidator extends TransactionResponseValidator
{
	const PIN_ONLY = "PIN_ONLY";
	const CANCELS_ENDPOINT = 'payments/v1/cancels';
	const MERCHANT_DETAILS_KEY = "merchantDetails";
	const REF_TXN_KEY = "referenceTransactionDetails";
	const REF_MERCHANT_TRANSACTION_KEY = "referenceTransactionId";

	private $httpAdapter;

	public function __construct(
		ResultInterfaceFactory $resultFactory,
		SubjectReader $subjectReader,
		ChHttpAdapter $httpAdapter,
		MultiLevelLogger $logger
	) {
		parent::__construct($resultFactory, $subjectReader, $logger);
		$this->httpAdapter = $httpAdapter;
		array_push($this->successStates, self::STATE_AUTHORIZED);
	}

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
			'merchantOrderId' => [HttpClient::RESPONSE_KEY, 'transactionDetails'],
			'transactionState' => [HttpClient::RESPONSE_KEY, 'gatewayResponse'],
			'detailedCardProduct' => [HttpClient::RESPONSE_KEY, 'detailedCardProduct'],
			self::MERCHANT_DETAILS_KEY => [HttpClient::RESPONSE_KEY, 'transactionDetails', 'merchantDetails'],
			HttpClient::STATUS_CODE_KEY => []
		];

		// Extract order ID from the validation subject
		$order = $this->subjectReader->readPayment($validationSubject)->getOrder();
		$orderIncrementId = $order->getOrderIncrementId();

		// Verify Status Code
		$statusCode = $this->subjectReader->getValueSafely($chRawResponse, HttpClient::STATUS_CODE_KEY, $paths[HttpClient::STATUS_CODE_KEY]);
		if (!$this->isStatusSuccessful($statusCode)) {
			array_push($errorMessages, "Something went wrong while processing CommerceHub transaction.");
			array_push($errorCodes, $statusCode);
			$this->logger->logError(2, "Transaction failure. Commerce Hub response returned with unsuccessful status", "Order ID: " . ($orderIncrementId ?? "Not found"));
			$this->logger->logError(2, "Status Code: " . $statusCode, "Order ID: " . ($orderIncrementId ?? "Not found"));

			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		// Extracting data from the response
		$pinOnlyState = $this->subjectReader->getValueSafely($chRawResponse, 'detailedCardProduct', $paths['detailedCardProduct']);
		$transactionId = $this->subjectReader->getValueSafely($chRawResponse, 'transactionId', $paths['transactionId']);
		$merchantDetails = $this->subjectReader->getValueSafely($chRawResponse, self::MERCHANT_DETAILS_KEY, $paths[self::MERCHANT_DETAILS_KEY]);
		$orderIncrementId = $this->subjectReader->getValueSafely($chRawResponse, 'merchantOrderId', $paths['merchantOrderId']);

		// Check for PIN only condition
		if ($pinOnlyState === self::PIN_ONLY) {
			array_push($errorMessages, "Invalid transaction processed for online payment: " . $pinOnlyState);
			array_push($errorCodes, $pinOnlyState);

			$this->logger->logError(2, "Invalid transaction processed for online payment. Canceling...", "Order ID: $orderIncrementId");
			$this->logger->logError(2, "Invalid transaction ID: " . $transactionId, "Order ID: $orderIncrementId");
			$this->logger->logError(2, "Invalid reason: " . $pinOnlyState, "Order ID: $orderIncrementId");

			$payload = [
				self::MERCHANT_DETAILS_KEY => $merchantDetails,
				self::REF_TXN_KEY => [
					self::REF_MERCHANT_TRANSACTION_KEY => $transactionId
				]
			];

			// Send Cancel Request
			$cancelResponse = $this->httpAdapter->sendRequest($payload, self::CANCELS_ENDPOINT);
			$cancelResponseDecoded = json_decode($cancelResponse->getBody(), true);
			if (isset($cancelResponseDecoded["gatewayResponse"]["transactionProcessingDetails"]["transactionId"])) {
				$this->logger->logError(2, "Cancel Transaction ID: " . $cancelResponseDecoded["gatewayResponse"]["transactionProcessingDetails"]["transactionId"], "Order ID: $orderIncrementId");
			}
			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		// Call parent for additional validation if needed
		$parentResult = parent::validate($validationSubject);
		if (!$parentResult->isValid()) {
			return $parentResult;
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
}
