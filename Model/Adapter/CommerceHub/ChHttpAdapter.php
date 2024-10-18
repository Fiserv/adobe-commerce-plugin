<?php
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Source\CommerceHub\ApiEnvironment;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpResponse;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Fiserv\Payments\Lib\Version;
use Fiserv\Payments\Logger\MultiLevelLogger;

class ChHttpAdapter
{
	const CONTENT_TYPE = 'application/json';
	const USER_AGENT_PREFIX = 'Fiserv-CommerceHub Adobe Commerce Plugin - v';

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

		return $this->execHttpRequest($payload, $url);
	}

	private function execHttpRequest($payload, $url) {
		$curl = $this->curlFactory->create();
		$curl->setConfig(
			[
				CURLOPT_TIMEOUT => 15,
				CURLOPT_USERAGENT => $this->getUserAgent(),
				CURLOPT_SSL_VERIFYHOST => 0,
			]
		);
		$curl->write('POST', $url, '1.1', $this->getHeaders($payload), $payload);
		$curlResponse = $curl->read();
		
		if($curl->getErrno()) {
			$this->logger->logError(2, "Curl error: ErrNo - " . $curl->getErrno() . "      Message - " . $curl->getError());
		}

		$statusCode = $curl->getInfo(CURLINFO_HTTP_CODE);
		$headerLength = $curl->getInfo(CURLINFO_HEADER_SIZE);

		$curl->close();

		return new ChHttpResponse($statusCode, $curlResponse, $headerLength);
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
