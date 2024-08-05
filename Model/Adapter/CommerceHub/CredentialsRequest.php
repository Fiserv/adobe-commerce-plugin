<?php
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Source\CommerceHub\ApiEnvironment;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Magento\Store\Model\StoreManagerInterface;

class CredentialsRequest
{
	const CREDENTIALS_ENDPOINT = 'payments-vas/v1/security/credentials';
	
	// CommerceHub Credentials Request Keys
	const KEY_DOMAINS = 'domains';
	const KEY_URL = 'url';
	const KEY_MERCHANT_DETAILS = 'merchantDetails';
	const KEY_MERCHANT_ID = 'merchantId';
	const KEY_KEY_ID = 'keyId';

	// CommerceHub Credentials Response Keys
	const KEY_SYMMETRIC_ENCRYPTION_ALGO = 'symmetricEncryptionAlgorithm';
	const KEY_ACCESS_TOKEN = 'accessToken';
	const KEY_SESSION_ID = 'sessionId';
	const KEY_PUBLIC_KEY = 'publicKey';


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
		StoreManagerInterface $storeManager
	) {
		$this->chConfig = $config;
		$this->httpAdapter = $httpAdapter;
		$this->storeManager = $storeManager;
	}

	/**
	 * Retrieve assoc array of 
	 * CommerceHub CredentialsRequest info
	 *
	 * @return array
	 */
	public function requestCredentials()
	{
		$data = $this->getCredentialsPayload($this->getMerchantId());
		$chResponse = $this->httpAdapter->sendRequest($data, self::CREDENTIALS_ENDPOINT);
		return $this->parseChCredentialsResponse($chResponse);
	}

	private function parseChCredentialsResponse($chResponse) {
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

		$bodyArray = json_decode($body, true);

		$data = [];
		if ($statusCode === 201) {
			$data[self::KEY_SYMMETRIC_ENCRYPTION_ALGO] = $bodyArray[self::KEY_SYMMETRIC_ENCRYPTION_ALGO];
			$data[self::KEY_ACCESS_TOKEN] = $bodyArray[self::KEY_ACCESS_TOKEN];
			$data[self::KEY_SESSION_ID] = $bodyArray[self::KEY_SESSION_ID];
			$data[self::KEY_PUBLIC_KEY] = $bodyArray[self::KEY_PUBLIC_KEY];
			$data[self::KEY_KEY_ID] = $bodyArray[self::KEY_KEY_ID];
		} else {
			throw new \Exception('CommerceHub credentials request HTTP error code: ' . $statusCode, 1);
		};

		return $data;
	}

	/**
	 * Retrieve assoc array of Payment.JS
	 * authorization information
	 *
	 * @param string $merchantId
	 * @return array
	 */
	private function getCredentialsPayload($merchantId) {
		$payload = [];
		$domains = [];
		$urls = []; 
		$urls[self::KEY_URL] = $this->getStoreBaseUrl();
		array_push($domains, $urls);
		$payload[self::KEY_DOMAINS] = $domains;
		$merchantDetails = [];
		$merchantDetails[self::KEY_MERCHANT_ID] = $merchantId;
		$payload[self::KEY_MERCHANT_DETAILS] = $merchantDetails;

		return $payload;
	}

	/**
	 * Retreive base url of Magento store
	 * 
	 * @return string
	 */
	private function getStoreBaseUrl() {
		return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
	}

	private function getMerchantId() {
		return $this->chConfig->getMerchantId();
	}
}
