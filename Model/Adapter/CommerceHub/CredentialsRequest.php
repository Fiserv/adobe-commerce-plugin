<?php
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\ApplePay\Config as ApplePayConfig;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
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
	const KEY_CUSTOMER_ID = "id";
	const KEY_AMOUNT = "amount";
	const KEY_BILLING_ADDRESS = "billingAddress";
	const KEY_PAYMENT_TOKEN = "paymentToken";
	const KEY_SOURCE = "source";
	const KEY_3DS = "threeDSecure";
	const KEY_TRANSACTION_DETAILS = "transactionDetails";
	const KEY_AUTHENTICATION_3DS = "authentication3DS";
	const KEY_ADDITIONAL_DATA_COMMON = "additionalDataCommon";
	const KEY_DYNAMIC_DESCRIPTORS = "dynamicDescriptors";
	const KEY_ADDITIONAL_DATA = "additionalData";
	const KEY_ECOM_URL = "ecomUrl";
	const KEY_ORDER_DATA = "orderData";

	// Response Keys
	const KEY_SYMMETRIC_ENCRYPTION_ALGO = 'symmetricEncryptionAlgorithm';
	const KEY_ACCESS_TOKEN = 'accessToken';
	const KEY_SESSION_ID = 'sessionId';
	const KEY_PUBLIC_KEY = 'publicKey';

	/** @var ApplePayConfig */
	private ApplePayConfig $applePayConfig;

	/** @var CommerceHubConfig */
	private CommerceHubConfig $commerceHubConfig;

	/** @var ChHttpAdapter */
	private ChHttpAdapter $httpAdapter;

	/** @var StoreManagerInterface */
	private StoreManagerInterface $storeManager;

	/** @var MultiLevelLogger */
	private MultiLevelLogger $logger;

	/** @var VaultPaymentTokenUtils */
	private VaultPaymentTokenUtils $vaultUtils;

	/** @var string */
	private string $paymentMethodCode;

	public function __construct(
		ApplePayConfig $applePayConfig,
		CommerceHubConfig $commerceHubConfig,
		ChHttpAdapter $httpAdapter,
		StoreManagerInterface $storeManager,
		MultiLevelLogger $logger,
		VaultPaymentTokenUtils $vaultUtils,
		$paymentMethodCode = 'fiserv_commercehub'
	) {
		$this->applePayConfig = $applePayConfig;
		$this->commerceHubConfig = $commerceHubConfig;
		$this->httpAdapter = $httpAdapter;
		$this->storeManager = $storeManager;
		$this->logger = $logger;
		$this->vaultUtils = $vaultUtils;
		$this->paymentMethodCode = $paymentMethodCode;
	}

	public function requestCredentials(array $sessionData): array
	{
		$this->logger->logInfo(1, "Initiating Credentials Request");

		$merchantId = $this->getMerchantId($sessionData);
		$data = $this->getCredentialsPayload($merchantId, $sessionData);

		$missingFields = [];
		if (empty($data[self::KEY_DOMAINS])) {
			$missingFields[] = self::KEY_DOMAINS;
		}
		if (empty($data[self::KEY_MERCHANT_DETAILS][self::KEY_MERCHANT_ID])) {
			$missingFields[] = self::KEY_MERCHANT_ID;
		}

		if (!empty($missingFields)) {
			$errorMsg = 'Missing required fields: ' . implode(', ', $missingFields);
			$this->logger->logError(1, $errorMsg);
			throw new \Exception($errorMsg);
		}

		try {
			$response = $this->httpAdapter->sendRequest($data, self::CREDENTIALS_ENDPOINT);
			$this->logger->logInfo(2, "Raw Response: " . var_export($response, true));
			return $this->parseChCredentialsResponse($response);
		} catch (\Exception $e) {
			$this->logger->logError(1, "Exception in sendRequest: " . $e->getMessage());
			$this->logger->logError(2, $e->getTraceAsString());
			throw $e;
		}
	}

	private function parseChCredentialsResponse($response): array
	{
		$statusCode = $response->getStatusCode();
		$body = $response->getBody();
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
			$this->logger->logError(2, 'HTTP error code: ' . $statusCode);
			$this->logger->logError(2, 'Response body: ' . json_encode($bodyArray));
			throw new \Exception('Credentials request HTTP error code: ' . $statusCode, 1);
		}

		return $data;
	}

	private function getCredentialsPayload(string $merchantId, array $sessionData): array
	{
		$payload = [];

		$payload[self::KEY_DOMAINS] = [[self::KEY_URL => $this->getStoreBaseUrl()]];
		$payload[self::KEY_MERCHANT_DETAILS] = [self::KEY_MERCHANT_ID => $merchantId];

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
			$payload[self::KEY_SOURCE] = $this->buildPaymentTokenSource(
				$sessionData[self::KEY_PAYMENT_TOKEN],
				$sessionData[self::KEY_CUSTOMER][self::KEY_CUSTOMER_ID] ?? ''
			);
		}

		if (!empty($sessionData[self::KEY_3DS])) {
			$payload[self::KEY_TRANSACTION_DETAILS] = [self::KEY_AUTHENTICATION_3DS => true];
		}

		if (isset($sessionData[self::KEY_ORDER_DATA])) {
			$payload[self::KEY_ORDER_DATA] = $sessionData[self::KEY_ORDER_DATA];
		}

		$payload[self::KEY_ADDITIONAL_DATA_COMMON] = [
			self::KEY_ADDITIONAL_DATA => [
				self::KEY_ECOM_URL => $this->getStoreBaseUrl()
			]
		];

		$payload[self::KEY_DYNAMIC_DESCRIPTORS] = [
			'merchantName' => 'VirtualShop',
			'address' => [
				'country' => 'US'
				]
			];
		return $payload;
	}

	private function buildPaymentTokenSource(string $tokenData, string $customerId): array
	{
		$token = $this->vaultUtils->getByGatewayToken($tokenData, $this->paymentMethodCode, $customerId);

		if (is_null($token) || count($token) < 1) {
			throw new \Exception("Unable to locate payment token for session.");
		}

		$details = json_decode($token[0]["details"], true);

		if (is_null($details) || !isset($details["expirationDate"])) {
			throw new \Exception("Missing expiration date in payment token.");
		}

		list($month, $year) = explode('/', $details["expirationDate"]);

		return [
			"sourceType" => "PaymentToken",
			"tokenData" => $tokenData,
			"tokenSource" => "TRANSARMOR",
			"card" => [
				"expirationMonth" => $month,
				"expirationYear" => $year
			]
		];
	}

	private function getStoreBaseUrl(): string
	{
		return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
	}

	private function getMerchantId(array $sessionData): string
	{
		return $this->getActiveConfig($sessionData)->getMerchantId();
	}

	private function getActiveConfig(array $sessionData): ApplePayConfig|CommerceHubConfig
	{
		$type = $sessionData['type'] ?? 'commercehub';
		return $type === 'applepay' ? $this->applePayConfig : $this->commerceHubConfig;
	}
}
