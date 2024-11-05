<?php
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Source\CommerceHub\ApiEnvironment;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpResponse;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Fiserv\Payments\Lib\Version;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Http\ClientException;

class ChHttpAdapter
{
	const CONTENT_TYPE = 'application/json';
	const USER_AGENT_PREFIX = 'Fiserv-CommerceHub Adobe Commerce Plugin - v';
	const INQUIRY_ENDPOINT = 'payments/v1/transaction-inquiry';
	const CANCELS_ENDPOINT = 'payments/v1/cancels';
	const MERCHANT_DETAILS_KEY = "merchantDetails";
	const REFERENCE_TRANSACTION_DETAILS_KEY = "referenceTransactionDetails";
	const REFENCE_MERCHANT_TRANSACTION_KEY = "referenceMerchantTransactionId";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var Config
	 */
	private $chConfig;

	/**
	 * @var CurlFactory
	 */
	private $curlFactory;

	/**
	 * @var string
	 */
	private $nonce;

	/**
	 * @var string
	 */
	private $timestamp;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 * @param MultiLevelLogger $logger
	 */
	public function __construct(
		Config $config,
		CurlFactory $curlFactory,
		MultiLevelLogger $logger
	) {
		$this->chConfig = $config;
		$this->curlFactory = $curlFactory;
		$this->logger = $logger;
	}

	/**
	 * Performs a credential request to CommerceHub API
	 * to authorize subsequent transactions
	 *
	 * @return array
	 */
	public function sendRequest($data, $endpoint)
	{
		$this->timestamp = $this->getTimestamp();
		$this->nonce = $this->getnonce($this->timestamp);

		$url = $this->getServiceUrl() . '/' . $endpoint;
		$payload = json_encode($data);

		return $this->execHttpRequest($payload, $url, $endpoint);
	}

	private function execHttpRequest($payload, $url, $endpoint) {
		$headerLength = 0;
		$curl = $this->generateBaseCurl(22, $headerLength);
		$curl->write('POST', $url, '1.1', $this->getHeaders($payload), $payload);
		$curlResponse = $curl->read();

		if($curl->getErrno()) {
			$this->logger->logError(2, "Curl error: ErrNo - " . $curl->getErrno() . "      Message - " . $curl->getError());
			// Error code 28 is the timeout error code
			$data = json_decode($payload, true);
			if(array_key_exists("transactionDetails", $data) && $curl->getErrno() === 28) {
				$this->logger->logCritical(1, "Timeout detected. Attempting recovery...");
				$curl->close();
				// Step 1: Idempotency attempt
				$this->logger->logCritical(1, "Initiating idempotency attempt for Client-Request-Id " . $this->nonce);
				$headerLength = 0;
				$this->timestamp = $this->getTimestamp();
				$curl = $this->generateBaseCurl(2, $headerLength);
				$curl->write('POST', $url, '1.1', $this->getHeaders($payload), $payload);
				$curlResponse = $curl->read();
				if($curl->getErrno()) {
					$this->logger->logCritical(1, "Idempotency attempt failure. Continuing recovery process...");
					$curl->close();
					return $this->handleTimeout(json_decode($payload, true), $endpoint);
				}
				$this->logger->logInfo(1, "Idempotency attempt success. Returning from recovery process...");
			}
		}

		$statusCode = $curl->getInfo(CURLINFO_HTTP_CODE);

		$curl->close();
		return new ChHttpResponse($statusCode, $curlResponse, $headerLength);
	}

	/**
	 * Handles timeout by sending an inquiry transaction and responding accordingly
	 */
	private function handleTimeout($data, $endpoint)
	{
		// Step 2: Transaction Inquiry
		$this->timestamp = $this->getTimestamp();
		$this->nonce = $this->getnonce($this->timestamp);
		$transactionID = $data["transactionDetails"]["merchantTransactionId"];
		$this->logger->logCritical(1, "Initiating transaction inquiry for referenceMerchantTransactionId " . $transactionID);
		$payload = json_encode(
			[
				self::MERCHANT_DETAILS_KEY => $data[self::MERCHANT_DETAILS_KEY],
				self::REFERENCE_TRANSACTION_DETAILS_KEY => [
					self::REFENCE_MERCHANT_TRANSACTION_KEY => $transactionID
				]
			]
		);
		
		$inquiryUrl = $this->getServiceUrl() . '/' . self::INQUIRY_ENDPOINT;
		$inquiryHeaderLength = 0;
		$inquiryCurl = $this->generateBaseCurl(2, $inquiryHeaderLength);
		$inquiryCurl->write('POST', $inquiryUrl, '1.1', $this->getHeaders($payload), $payload);
		$inquiryResponseFull = $inquiryCurl->read();
		$inquiryResponseArray = json_decode(substr($inquiryResponseFull, $inquiryHeaderLength), true);

		// Response returned from Commerce Hub. No errors. Transaction exists
		if(!$inquiryCurl->getErrno() && $inquiryResponseArray !== array())
		{
			$inquiryStatusCode = $inquiryCurl->getInfo(CURLINFO_HTTP_CODE);

			// Look for correct transaction within $inquiryResponse and return just the body
			$orderID = $data["transactionDetails"]["merchantOrderId"];
			$inquiryBody;
			foreach($inquiryResponseArray as $response) {
				if($response["transactionDetails"]["merchantOrderId"] === $orderID) {
					$inquiryBody = $response;
				}
			}

			$this->logger->logInfo(1, "Transaction inquiry success. Returning from recovery process...");
			$inquiryCurl->close();
			return new ChHttpResponse($inquiryStatusCode, json_encode($inquiryBody), 0);
		}
		$this->logger->logCritical(1, "Transaction inquiry failure. Continuing recovery process...");

		// Step 3: Critical Recovery (Deal with transaction specific response flows if issue with inquiry occurred)
		if($endpoint === "payments/v1/charges" && $data["transactionDetails"]["captureFlag"] === false)
		{
			// Attempt cancel transaction of initial Auth
			$this->logger->logCritical(1, "Auth detected. Attempting to Cancel initial transaction...");
			$this->timestamp = $this->getTimestamp();
			$this->nonce = $this->getnonce($this->timestamp);
			
			$cancelUrl = $this->getServiceUrl() . '/' . self::CANCELS_ENDPOINT;
			$cancelCurl = $this->generateBaseCurl(2);
			$cancelCurl->write('POST', $cancelUrl, '1.1', $this->getHeaders($payload), $payload);
			$cancelResponse = $cancelCurl->read();

			if($cancelCurl->getErrno()) {
				$this->logger->logEmergency(1, "Initial transaction cancel failure. Failed to recover from transaction timeout. referenceMerchantTransactionId: " . $transactionID);
			} else {
				$this->logger->logCritical(1, "Cancel transaction successful. Recovery process finished.");
			}
			$cancelCurl->close();
		} else {
			// Do nothing  :(
			$this->logger->logEmergency(1, "Non-Auth transaction detected. Further recovery attempts not possible. Failed to recover from transaction timeout. referenceMerchantTransactionId: " . $transactionID);
		}
		// Error Out
		throw new ClientException(
			__('Timeout occurred during transaction.')
		);
	}

	/**
	 * Fills in base information of a curl object
	 *
	 * @param &int $headerLength
	 * @param int $timeout
	 */
	private function generateBaseCurl($timeout, &$headerLength = null)
	{
		$curl = $this->curlFactory->create();
		$curl->setConfig(
			[
				CURLOPT_TIMEOUT => $timeout,
				CURLOPT_USERAGENT => $this->getUserAgent(),
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_HEADERFUNCTION =>
				function($curl, $header) use (&$headerLength)
				{
					$len = strlen($header);
					$headerCheck = explode(':', $header, 2);
					// transfer-encoding gets purged for some reason, don't add to length
					if($headerLength === null || $headerCheck[0] === 'transfer-encoding')
						return $len;
					$headerLength += $len;
					return $len;
				}
			]
		);
		return $curl;
	}

	private function getUserAgent() 
	{
		return self::USER_AGENT_PREFIX . Version::getVersionString();
	}

	/**
	 * Creates assoc array of headers required
	 * for CommerceHub request
	 * See: https://developer.fiserv.com/product/CommerceHub/docs/?path=docs/Resources/API-Documents/Use-Our-APIs.md&branch=main#request-header
	 */
	private function getHeaders($payload) {
		return [
			'Api-Key: ' . $this->getChApiKey(),
			'Content-Type: ' . self::CONTENT_TYPE,
			'Content-Length: ' . strlen($payload),
			'Authorization: ' . $this->createSignature($payload),
			'Client-Request-Id: ' . $this->nonce,
			'Timestamp: ' . $this->timestamp,
			'Auth-Token-Type: HMAC' 
		];
	}

	/** 
	* Returns CommerceHub service url
	* based on gateway's environment
	* 
	* @param string $storeId
	* @return string
	*/ 
	private function getServiceUrl() {
		$env = $this->chConfig->getApiEnvironment();
		if ($env == ApiEnvironment::ENVIRONMENT_PROD) {
			return $this->chConfig->getProdApiService();
		} else if ($env == ApiEnvironment::ENVIRONMENT_CERT) {
			return $this->chConfig->getCertApiService();
		}
	}

	private function createSignature($payload) {
		$msg = $this->getChApiKey() . $this->nonce . $this->timestamp . $payload;
		return base64_encode(hash_hmac('sha256', $msg, $this->getChApiSecret()));
	}

	private function getChApiKey() {
		return $this->chConfig->getApiKey();
	}

	private function getChApiSecret() {
		return $this->chConfig->getApiSecret();
	}

	private function getMerchantId() {
		return $this->chConfig->getMerchantId();
	}

	private function getNonce($timestamp) {
		return $timestamp + rand();
	}

	private function getTimestamp() {
		return floor(microtime(true) * 1000);
	}

}
