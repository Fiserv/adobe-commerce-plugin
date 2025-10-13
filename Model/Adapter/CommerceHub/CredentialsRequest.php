<?php
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Gateway\Config\PayPal\Config as PayPalConfig;
use Fiserv\Payments\Model\Source\CommerceHub\ApiEnvironment;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\System\Utils\VaultPaymentTokenUtils;

class CredentialsRequest
{
	const CREDENTIALS_ENDPOINT = 'payments-vas/v1/security/credentials';
	
	// CommerceHub Credentials Request Keys
	const KEY_DOMAINS = 'domains';
	const KEY_URL = 'url';
	const KEY_MERCHANT_DETAILS = 'merchantDetails';
	const KEY_MERCHANT_ID = 'merchantId';
	const KEY_KEY_ID = 'keyId';
	const KEY_CUSTOMER = "customer";
	const KEY_CUSTOMER_ID_COMMERCEHUB = "id";
	const KEY_CUSTOMER_ID_PAYPAL = "providerCustomerId";
	const KEY_AMOUNT = "amount";
	const KEY_BILLING_ADDRESS = "billingAddress";
	const KEY_PAYMENT_TOKEN = "paymentToken";
	const KEY_SOURCE = "source";
	const KEY_3DS = "threeDSecure";
	const KEY_TRANSACTION_DETAILS = "transactionDetails";
	const KEY_AUTHENTICATION_3DS = "authentication3DS";
	const KEY_ADDITIONAL_DATA_COMMON = "additionalDataCommon";
	const KEY_ADDITIONAL_DATA = "additionalData";
	const KEY_ECOM_URL = "ecomUrl";
	const KEY_PROVIDER_CREDENTIALS = "providerCredentials";
	const KEY_API_KEY = "apiKey";
	const KEY_API_SECRET = "apiSecret";
	const KEY_TERMINAL_ID = "terminalId";

	const CODE_PAYMENT_METHOD_COMMERCEHUB = "fiserv_commercehub";
	const CODE_PAYMENT_METHOD_PAYPAL = "fiserv_paypal";

	// CommerceHub Credentials Response Keys
	const KEY_SYMMETRIC_ENCRYPTION_ALGO = 'symmetricEncryptionAlgorithm';
	const KEY_ACCESS_TOKEN = 'accessToken';
	const KEY_SESSION_ID = 'sessionId';
	const KEY_PUBLIC_KEY = 'publicKey';

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var Config
	 */
	private $chConfig;

	/**
	 * @var PayPalConfig
	 */
	private $payPalConfig;

	private $vaultUtils;

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
	 * @param ChHttpAdapter $chHttpAdapter
	 * @param StoreManagerInterface $storeManager
	 * @param MultiLevelLogger $logger
	 * @param VaultPaymentTokenUtils $vaultUtils
	 * @param PayPalConfig|null $payPalConfig
	 */
	public function __construct(
		Config $config,
		ChHttpAdapter $chHttpAdapter,
		StoreManagerInterface $storeManager,
		MultiLevelLogger $logger,
		VaultPaymentTokenUtils $vaultUtils,
		PayPalConfig $payPalConfig = null
	) {
		$this->chConfig = $config;
		$this->storeManager = $storeManager;
		$this->logger = $logger;
		$this->vaultUtils = $vaultUtils;
		$this->httpAdapter = $chHttpAdapter;
		$this->payPalConfig = $payPalConfig;
	}

	/**
	 * Retrieve assoc array of CredentialsRequest info
	 *
	 * @return array
	 */
	public function requestCredentials(Array $sessionData)
		{
			$this->logger->logInfo(1, "Initiating Credentials Request");
			$data = $this->getCredentialsPayload($this->getMerchantId(), $sessionData);
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
			$this->logger->logInfo(1, "Credentials request success");
		} else {
			$this->logger->logError(1, "Credentials request failure");
			$this->logger->logError(2, 'CommerceHub credentials request HTTP error code: ' . $statusCode);
			$this->logger->logError(2, 'CommerceHub credentials request body: ' . json_encode($bodyArray));
			
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
	private function getCredentialsPayload($merchantId, $sessionData) {
$payload = [];
		$domains = [];
		$urls = []; 
		$urls[self::KEY_URL] = $this->getStoreBaseUrl();
		array_push($domains, $urls);
		$payload[self::KEY_DOMAINS] = $domains;
		$merchantDetails = [];
		$merchantDetails[self::KEY_MERCHANT_ID] = $merchantId;
		$payload[self::KEY_MERCHANT_DETAILS] = $merchantDetails;

		if (isset($sessionData[self::KEY_AMOUNT])) {
			$payload[self::KEY_AMOUNT] = $sessionData[self::KEY_AMOUNT];
		}		
		
		if (isset($sessionData[self::KEY_CUSTOMER])) {
			$payload[self::KEY_CUSTOMER] = $sessionData[self::KEY_CUSTOMER];
		}	

		if (isset($sessionData[self::KEY_BILLING_ADDRESS])) {
			$payload[self::KEY_BILLING_ADDRESS] = $sessionData[self::KEY_BILLING_ADDRESS];
		}

		if (isset($sessionData[self::KEY_PAYMENT_TOKEN])) {
			// Customer ID should be set, because only customers can use payment tokens
			$payload[self::KEY_SOURCE] = $this->buildPaymentTokenSource($sessionData[self::KEY_PAYMENT_TOKEN], $sessionData[self::KEY_CUSTOMER][self::KEY_CUSTOMER_ID]);	
		}	

		// Add providerCredentials with customerId attribute as requested, only if not guest
		$customerIdValue = 'guest';
		if (isset($sessionData[self::KEY_CUSTOMER][self::KEY_CUSTOMER_ID_PAYPAL])) {
			$customerIdValue = $sessionData[self::KEY_CUSTOMER][self::KEY_CUSTOMER_ID_PAYPAL];
		}
		if ($customerIdValue !== 'guest') {
			$payload[self::KEY_PROVIDER_CREDENTIALS] = [
				[
					'credentialType' => 'PAYPAL',
					'attributes' => [
						[
							'key' => 'customerId',
							'value' => $customerIdValue
						]
					]
				]
			];
		}
			
		if (isset($sessionData[self::KEY_3DS]) && $sessionData[self::KEY_3DS] === true) {
			$payload[self::KEY_TRANSACTION_DETAILS] = array(	
				self::KEY_AUTHENTICATION_3DS => true
			);
		}

		$payload[self::KEY_ADDITIONAL_DATA_COMMON] = array(
			self::KEY_ADDITIONAL_DATA => array(
				self::KEY_ECOM_URL => $this->getStoreBaseUrl() 
			)
		);

		return $payload;
	}

	private function buildPaymentTokenSource(string $tokenData, string $customerId) : array
	{
		$token = $this->vaultUtils->getByGatewayToken($tokenData, self::CODE_PAYMENT_METHOD_COMMERCEHUB, $customerId);
		if (is_null($token) || count($token) < 1)
		{
			throw new \Exception("Unable to locate payment token for Commercehub payment session with source.");
		}

		$details = json_decode($token[0]["details"], true);
		if (is_null($details) || !isset($details["expirationDate"]))
		{	
			throw new \Exception("Unable to locate payment token expiration date for Commercehub payment session with source.");
		}	

		list($month, $year) = explode('/', $details["expirationDate"]);
		return array(
			"sourceType" => "PaymentToken",
			"tokenData" => $tokenData,
			"tokenSource" => "TRANSARMOR",
			"card" => array(
				"expirationMonth" => $month,
				"expirationYear" => $year
			)
		);
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
