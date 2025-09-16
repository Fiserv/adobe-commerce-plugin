<?php
namespace Fiserv\Payments\Model\Adapter\PayPal;

use Fiserv\Payments\Gateway\Config\PayPal\Config;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as chConfig;
use Fiserv\Payments\Model\Source\PayPal\ApiEnvironment;
use Fiserv\Payments\Model\Adapter\PayPal\PayPalHttpResponse;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Fiserv\Payments\Lib\Version;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Store\Model\StoreManagerInterface;

class PayPalHttpAdapter
{
	const CONTENT_TYPE = 'application/json';
	const USER_AGENT_PREFIX = 'Fiserv-PayPal Adobe Commerce Plugin - v';
	const INQUIRY_ENDPOINT = 'checkouts/v1/inquiry';
	const CANCELS_ENDPOINT = 'checkouts/v1/orders';
	const MERCHANT_DETAILS_KEY = "merchantDetails";
	const REFERENCE_TRANSACTION_DETAILS_KEY = "referenceTransactionDetails";
	const REFENCE_MERCHANT_TRANSACTION_KEY = "referenceMerchantTransactionId";
	// Define PayPal-specific endpoints and constants as needed

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var Config
	 */
		private $paypalConfig;
		private $chConfig;

	/**
	 * @var CurlFactory
	 */
	private $curlFactory;

	/**
	 * @var string
	 */
		private $nonce;
		private $timestamp;
		private $storeManager;
		private $storeId;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 * @param MultiLevelLogger $logger
	 */
	   public function __construct(
		   Config $config,
		   chConfig $commerceHubConfig,
		   CurlFactory $curlFactory,
		   MultiLevelLogger $logger,
		   StoreManagerInterface $storeManager
	   ) {
		$this->paypalConfig = $config;
		$this->chConfig = $commerceHubConfig;
		   $this->curlFactory = $curlFactory;
		   $this->logger = $logger;
		   $this->storeManager = $storeManager;
		   $this->storeId = $this->storeManager->getStore()->getId();
	   }

	/**
	 * Performs a credential request to PayPal API
	 * to authorize subsequent transactions
	 *
	 * @return array
	 */
	public function sendRequest($data, $endpoint)
	{
		$this->timestamp = $this->getTimestamp();
		$this->nonce = $this->getNonce($this->timestamp);

		$url = $this->getServiceUrl() . '/' . $endpoint;
		$payload = json_encode($data);

		return $this->execHttpRequest($payload, $url, $endpoint);
	}

	private function execHttpRequest($payload, $url, $endpoint)
	{
		$curl = $this->generateBaseCurl(22);
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
				$this->timestamp = $this->getTimestamp();
				$curl = $this->generateBaseCurl(2);
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
		$headerLength = $curl->getInfo(CURLINFO_HEADER_SIZE);
		$curl->close();
		return new PayPalHttpResponse($statusCode, $curlResponse, $headerLength);
	}

	/**
	 * Handles timeout by attempting idempotency and logging the issue
	 */
	private function handleTimeout($data, $endpoint)
	{
		$transactionID = $data["transactionDetails"]["merchantTransactionId"] ?? 'unknown';
		$this->logger->logCritical(1, "Timeout detected for transaction ID: " . $transactionID . ". Recovery process initiated.");

		// Attempt idempotency with a simple retry
		try {
			$this->timestamp = $this->getTimestamp();
			$this->nonce = $this->getNonce($this->timestamp);
			$url = $this->getServiceUrl() . '/' . $endpoint;
			$payload = json_encode($data);

			$retryCurl = $this->generateBaseCurl(5); // Longer timeout for retry
			$retryCurl->write('POST', $url, '1.1', $this->getHeaders($payload), $payload);
			$retryResponse = $retryCurl->read();

			if ($retryCurl->getErrno()) {
				$this->logger->logError(2, "Idempotency retry failed for transaction ID: " . $transactionID);
				$retryCurl->close();
				throw new ClientException(__('Transaction timeout: Unable to complete payment. Please try again.'));
			}

			$retryStatusCode = $retryCurl->getInfo(CURLINFO_HTTP_CODE);
			$retryHeaderLength = $retryCurl->getInfo(CURLINFO_HEADER_SIZE);
			$retryCurl->close();

			$this->logger->logInfo(1, "Idempotency retry successful for transaction ID: " . $transactionID);
			return new PayPalHttpResponse($retryStatusCode, $retryResponse, $retryHeaderLength);

		} catch (\Exception $e) {
			$this->logger->logEmergency(1, "Timeout recovery failed for transaction ID: " . $transactionID . ". Error: " . $e->getMessage());
			throw new ClientException(__('Transaction timeout: Unable to complete payment. Please contact support if the issue persists.'));
		}
	}

	/**
	 * Fills in base information of a curl object
	 *
	 * @param int $timeout
	 */
	private function generateBaseCurl($timeout)
	{
		$curl = $this->curlFactory->create();
		$curl->setConfig(
			[
				CURLOPT_TIMEOUT => $timeout,
				CURLOPT_USERAGENT => $this->getUserAgent(),
				CURLOPT_SSL_VERIFYHOST => 0
			]
		);
		return $curl;
	}

	/**
	 * Uses default php curl instead of curl factory to avoid Magento specific curl issues
	 *
	 * @param string $url
	 * @param string $payload
	 * @param int $timeout
	 */
	private function generateNakedBaseCurl($url, $payload, $timeout)
	{
		$curl = curl_init();
		curl_setopt_array($curl,
						  [
							  CURLOPT_URL => $url,
							  CURLOPT_RETURNTRANSFER => true,
							  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
							  CURLOPT_HTTPHEADER => $this->getHeaders($payload),
							  CURLOPT_HEADER => true,
							  CURLOPT_POSTFIELDS => $payload,
							  CURLOPT_POST => true,
							  CURLOPT_TIMEOUT => $timeout,
							  CURLOPT_USERAGENT => $this->getUserAgent(),
							  CURLOPT_SSL_VERIFYHOST => 0
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
		$apiKey = $this->getChApiKey();
		$signature = $this->createSignature($payload);

		$this->logger->logInfo(2, "API Key being used: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -10));
		$this->logger->logInfo(2, "Signature being used: " . substr($signature, 0, 20) . "...");
		$this->logger->logInfo(2, "Timestamp: " . $this->timestamp);
		$this->logger->logInfo(2, "Nonce: " . $this->nonce);

		return [
			'Api-Key: ' . $apiKey,
			'Content-Type: ' . self::CONTENT_TYPE,
			'Content-Length: ' . strlen($payload),
			'Authorization: ' . $signature,
			'Client-Request-Id: ' . $this->nonce,
			'Timestamp: ' . $this->timestamp,
			'Auth-Token-Type: HMAC'
		];
	}

	// This function isn't used, but we're gonna keep it here just in case it ever needs to be used...
	private function manualHeaderLengthCalculation($httpResponse)
	{
		$firstBracket = false;
		$bracketCount = 0;
		$inQuotes = false;
		for($i = strlen($httpResponse) - 1; $i > 0; $i--)
		{
			if($httpResponse[$i] === '"')
			{
				$slashCounter = 0;
				for($j = $i - 1; $j > 0 && $httpResponse[$j] === '\\'; $j--)
				{
					$slashCounter++;
				}
				if($slashCounter % 2 === 0)
				{
					$inQuotes = !$inQuotes;
				}
			}
			if(!$inQuotes)
			{
				if($httpResponse[$i] === ']' || $httpResponse[$i] === '}')
				{
					$bracketCount++;
					$firstBracket = true;
				}
				else if($httpResponse[$i] === '[' || $httpResponse[$i] === '{')
				{
					$bracketCount--;
				}
			}
			if($firstBracket && $bracketCount === 0)
			{
				return $i;
			}
		}
		return 0;
	}

/** 
	* Returns CommerceHub service url
	* based on gateway's environment
	* 
	* @param string $storeId
	* @return string
	*/ 
	   private function getServiceUrl() {
		   $env = $this->chConfig->getApiEnvironment($this->storeId);
		   if ($env == ApiEnvironment::ENVIRONMENT_PROD) {
			   return $this->chConfig->getProdApiService($this->storeId);
		   } else if ($env == ApiEnvironment::ENVIRONMENT_CERT) {
			   return $this->chConfig->getCertApiService($this->storeId);
		   } else if ($env == ApiEnvironment::ENVIRONMENT_QA) {
			   return $this->chConfig->getQaApiService($this->storeId);
		   }
	}

	   private function createSignature($payload) {
		   $apiKey = $this->getChApiKey();
		   $apiSecret = $this->getChApiSecret();
		   $msg = $apiKey . $this->nonce . $this->timestamp . $payload;

		   $this->logger->logInfo(2, "Creating signature with API Key: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -10));
		   $this->logger->logInfo(2, "API Secret length: " . strlen($apiSecret));
		   $this->logger->logInfo(2, "Message for signature: " . substr($msg, 0, 50) . "...");

		   $signature = base64_encode(hash_hmac('sha256', $msg, $apiSecret));
		   $this->logger->logInfo(2, "Generated signature: " . substr($signature, 0, 20) . "...");

		   return $signature;
	   }

	   private function getChApiKey() {
		   return $this->chConfig->getApiKey($this->storeId);
	   }

	   private function getChApiSecret() {
		   return $this->chConfig->getApiSecret($this->storeId);
	   }

	   private function getMerchantId() {
		   return $this->chConfig->getMerchantId($this->storeId);
	   }

	private function getNonce($timestamp) {
		return $timestamp + rand();
	}

	private function getTimestamp() {
		return floor(microtime(true) * 1000);
	}

	/**
	 * Capture a PayPal order
	 *
	 * @param string $paypalOrderId
	 * @return array
	 */
	public function captureOrder($paypalOrderId)
	{
		$data = [
			'orderId' => $paypalOrderId,
			'capture' => true
		];
		$endpoint = 'checkouts/v1/orders';
		$response = $this->sendRequest($data, $endpoint);
		return json_decode($response->getBody(), true);
	}

	/**
	 * Capture PayPal authorization
	 *
	 * @param string $paypalOrderId
	 * @return array
	 */
	public function captureAuthorization($paypalOrderId)
	{
		$data = [
			'orderId' => $paypalOrderId,
			'capture' => true
		];
		$endpoint = 'checkouts/v1/order'; 
		$response = $this->sendRequest($data, $endpoint);
		return json_decode($response->getBody(), true);
	}

}
