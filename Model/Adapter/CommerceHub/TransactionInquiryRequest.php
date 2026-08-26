<?php
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Source\CommerceHub\ApiEnvironment;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionInquiryRequest as InquiryRequest;
use Fiserv\Payments\Lib\CommerceHub\Model\ReferenceTransactionDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails;
use Fiserv\Payments\Gateway\Request\CommerceHub\SessionSourceDataBuilder;
use Fiserv\Payments\Logger\MultiLevelLogger;

class TransactionInquiryRequest
{
	const TXN_INQUIRY_ENDPOINT = 'payments/v1/transaction-inquiry';

	const RESPONSE_DESC_KEY = 'gatewayResponse';
	const TRANSACTION_STATE_KEY = 'transactionState';
	const FAILURE_STATE = 'FAILED';

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var Config
	 */
	private $chConfig;

	/**
	 * @var ChHttpAdapter
	 */
	private $httpAdapter;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 */
	public function __construct(
		Config $config,
		ChHttpAdapter $httpAdapter,
		MultiLevelLogger $logger
	) {
		$this->chConfig = $config;
		$this->httpAdapter = $httpAdapter;
		$this->logger = $logger;
	}

	/**
	 * Performs a credential request to CommerceHub API
	 * to authorize subsequent transactions
	 *
	 * @return array
	 */
	public function executeTransactionInquiry($refTxnId)
	{
		$this->logger->logInfo(1, "Initiating Transaction Inquiry Request");
		$data = $this->getTransactionInquiryPayload($refTxnId);
		$response = $this->httpAdapter->sendRequest($data, self::TXN_INQUIRY_ENDPOINT);

		return $this->parseTransactionInquiryResponse($response);
	}

	private function parseTransactionInquiryResponse($httpResponse) {
		$statusCode = $httpResponse->getStatusCode();
		$response = $httpResponse->getResponse();
		$body = $httpResponse->getBody();		
		$bodyArray = json_decode($body, true);

		if ($statusCode === 200 && $this->isInquirySuccessful($bodyArray)) {
			$this->logger->logInfo(1, "Transaction Inquiry request success");
			return $bodyArray;
		}
		$this->logger->logError(1, "Transaction Inquiry request failure");
		$this->logger->logError(2, 'CommerceHub transaction inquiry request HTTP error code: ' . $statusCode);
		throw new \Exception('CommerceHub transaction inquiry request HTTP error code: ' . $statusCode, 1);
	}

	private function isInquirySuccessful($bodyArray) {
		return 
			isset($bodyArray[0]) &&	
			isset($bodyArray[0][self::RESPONSE_DESC_KEY]) &&
			isset($bodyArray[0][self::RESPONSE_DESC_KEY][self::TRANSACTION_STATE_KEY]) &&
			$bodyArray[0][self::RESPONSE_DESC_KEY][self::TRANSACTION_STATE_KEY] !== self::FAILURE_STATE;
	}

	/**
	 * Retrieve assoc array of 
	 * CommerceHub TransactionInquiryRequest info
	 *
	 * @param string $sessionId
	 * @return array
	 */
	private function getTransactionInquiryPayload($refTxnId) {
		$refTxnDetails = new ReferenceTransactionDetails();
		$refTxnDetails->setReferenceTransactionId($refTxnId);

		$merchantDetails = new MerchantDetails();
		$merchantDetails->setMerchantId($this->getMerchantId());
		$merchantDetails->setTerminalId($this->getTerminalId());

		$req = new InquiryRequest();
		$req->setReferenceTransactionDetails($refTxnDetails);
		$req->setMerchantDetails($merchantDetails);

		return $req;
	}

	private function getMerchantId() {
		return $this->chConfig->getMerchantId();
	}

	private function getTerminalId() {
		return $this->chConfig->getTerminalId();
	}
}
