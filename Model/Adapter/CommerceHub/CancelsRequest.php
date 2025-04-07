<?php
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Source\CommerceHub\ApiEnvironment;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

class CancelsRequest
{
	const CANCELS_ENDPOINT = 'payments/v1/cancels';
	
	// CommerceHub Cancels Request Keys
	const KEY_MERCHANT_DETAILS = 'merchantDetails';
	const KEY_MERCHANT_ID = 'merchantId';
	const KEY_TERMINAL_ID = 'terminalId';
	const KEY_REFERENCE_TRANSACTION = 'referenceTransactionId';
	const KEY_REFERNCE_TRANSACTION_DETAILS = 'referenceTransactionDetails';

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var Config
	 */
	private $chConfig;

	/**
	 * @ChHttpAdapter
	 */
	private $httpAdapter;
	
	/**
	 * @StoreManagerInterface
	 */
	private $storeManager;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 */
	public function __construct(
		Config $config,
		ChHttpAdapter $httpAdapter,
		StoreManagerInterface $storeManager,
		MultiLevelLogger $logger
	) {
		$this->chConfig = $config;
		$this->httpAdapter = $httpAdapter;
		$this->storeManager = $storeManager;
		$this->logger = $logger;
	}

	public function requestCancel($referenceTransactionId)
	{
		$this->logger->logInfo(1, "Initiating Cancel Request");
		$data = $this->getCancelsPayload($this->getMerchantId(), $this->getTerminalId(), $referenceTransactionId);
		$chResponse = $this->httpAdapter->sendRequest($data, self::CANCELS_ENDPOINT);
		return $this->parseChCancelsResponse($chResponse);
	}

	private function parseChCancelsResponse($chResponse) {
		$statusCode = $chResponse->getStatusCode();
		$response = $chResponse->getResponse();
		$headerLength = $chResponse->getHeaderLength();
		$body = $chResponse->getBody();
		
		$header = [];

		foreach(explode("\r\n", trim(substr($response, 0, $headerLength))) as $row) {
			if(preg_match('/(.*?): (.*)/', $row, $matches)) {
				$header[$matches[1]] = $matches[2];
			}
		}

		return json_decode($body, true);
	}

	private function getCancelsPayload($merchantId, $terminalId, $referenceTransactionId) {
		$payload = array();
		$merchantDetails = array();
		$merchantDetails[self::KEY_MERCHANT_ID] = $merchantId;
		$merchantDetails[self::KEY_TERMINAL_ID] = $terminalId;
		$payload[self::KEY_MERCHANT_DETAILS] = $merchantDetails;

		$referenceTransactionDetails = array();
		$referenceTransactionDetails[self::KEY_REFERENCE_TRANSACTION] = $referenceTransactionId;
		$payload[self::KEY_REFERNCE_TRANSACTION_DETAILS] = $referenceTransactionDetails;
		
		return $payload;
	}

	private function getMerchantId() {
		return $this->chConfig->getMerchantId();
	}

	private function getTerminalId() {
		return $this->chConfig->getTerminalId();
	}
}
