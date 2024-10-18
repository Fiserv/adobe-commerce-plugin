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
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpResponse;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Validates the status of an attempted Auth transaction
 */
class AuthorizeResponseValidator extends TransactionResponseValidator
{
	const PIN_ONLY = "PIN_ONLY";
	const CANCELS_ENDPOINT = 'payments/v1/cancels';
	const MERCHANT_DETAILS_KEY = "merchantDetails";
	const REF_TXN_KEY = "referenceTransactionDetails";
	const REF_MERCHANT_TRANSACTION_KEY = "referenceTransactionId";

	/**
	 * @var ChHttpAdapter
	 */
	private $httpAdapter;

	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(ResultInterfaceFactory $resultFactory, SubjectReader $subjectReader, ChHttpAdapter $httpAdapter, MultiLevelLogger $logger)
	{
		parent::__construct($resultFactory, $subjectReader, $logger);
		$this->httpAdapter = $httpAdapter;
		array_push($this->successStates, self::STATE_AUTHORIZED);
	}

	public function validate(array $validationSubject): ResultInterface
	{
		$chRawResponse = $this->subjectReader->readChResponseFromResponse($validationSubject);

		$errorMessages = [];
		$errorCodes = [];

		$pinOnlyState = $chRawResponse[HttpClient::RESPONSE_KEY]["cardDetails"]["detailedCardProduct"];
		if($pinOnlyState === self::PIN_ONLY)
		{
			array_push($errorMessages, "Invalid transaction processed for online payment: " . $pinOnlyState);
			array_push($errorCodes, $pinOnlyState);

			$this->logger->logError(1, "Invalid transaction processed for online payment. Canceling...");
			$this->logger->logError(2, "Invalid transaction ID: " . $chRawResponse[HttpClient::RESPONSE_KEY]["gatewayResponse"]["transactionProcessingDetails"]["transactionId"]);
			$this->logger->logError(2, "Invalid reason: " . $pinOnlyState);

			$payload = [
				self::MERCHANT_DETAILS_KEY => $chRawResponse[HttpClient::RESPONSE_KEY][self::MERCHANT_DETAILS_KEY],
				self::REF_TXN_KEY => [
					self::REF_MERCHANT_TRANSACTION_KEY => $chRawResponse[HttpClient::RESPONSE_KEY]["gatewayResponse"]["transactionProcessingDetails"]["transactionId"]
				]
			];
			$cancelResponse = $this->httpAdapter->sendRequest($payload, self::CANCELS_ENDPOINT);
			$cancelResponse = json_decode($cancelResponse->getBody(), true);
			if(isset($cancelResponse["gatewayResponse"]) && isset($cancelResponse["gatewayResponse"]["transactionProcessingDetails"]) && isset($cancelResponse["gatewayResponse"]["transactionProcessingDetails"]["transactionId"]))
			{
				$this->logger->logError(2, "Cancel Transaction ID: " . $cancelResponse["gatewayResponse"]["transactionProcessingDetails"]["transactionId"]);
			}

			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		return parent::validate($validationSubject);
	}
}
