<?php
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Source\CommerceHub\ApiEnvironment;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\System\Utils\VaultPaymentTokenUtils;
use Fiserv\Payments\Lib\CommerceHub\Model\DynamicDescriptors;
use Fiserv\Payments\Lib\CommerceHub\Model\Address;

class CredentialsRequest
{
    // Commercehub Sessions Request Keys
	const CREDENTIALS_ENDPOINT = 'v1/sessions/create';
	const KEY_SESSION = 'session';
    const INTENT_AUTHORIZE = 'AUTHORIZE';
    const INTENT_CHECKOUT = 'CHECKOUT';
    const INTENT_CAPTURE = 'CAPTURE';
    const INTENT_INITIATOR_MERCHANT = 'MERCHANT';
    const CHECKOUT_TYPE_SDK = 'SDK';
    const KEY_SDK_DATA = 'sdkData';
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
	const KEY_SHIPPING_ADDRESS = "shippingAddress";
	const KEY_PAYMENT_TOKEN = "paymentToken";
	const KEY_SOURCE = "source";
	const KEY_3DS = "threeDSecure";
	const KEY_CHECKOUT_INTERACTIONS = 'checkoutInteractions';
	const KEY_TRANSACTION_DETAILS = "transactionDetails";
	const KEY_AUTHENTICATION_3DS = "authentication3DS";
	const KEY_ADDITIONAL_DATA_COMMON = "additionalDataCommon";
	const KEY_ADDITIONAL_DATA = "additionalData";
	const KEY_ECOM_URL = "ecomUrl";
	const KEY_PROVIDER_CREDENTIALS = "providerCredentials";
	const KEY_API_KEY = "apiKey";
	const KEY_API_SECRET = "apiSecret";
	const KEY_TERMINAL_ID = "terminalId";
    const KEY_ORDER_DATA = 'orderData';
    const KEY_DYNAMIC_DESCRIPTORS = 'dynamicDescriptors';
    const KEY_MERCHANT_NAME = 'merchantName';
    const KEY_COUNTRY = 'country';

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
	 */
	public function __construct(
		Config $config,
		ChHttpAdapter $chHttpAdapter,
		StoreManagerInterface $storeManager,
		MultiLevelLogger $logger,
		VaultPaymentTokenUtils $vaultUtils
	) {
		$this->chConfig = $config;
		$this->storeManager = $storeManager;
		$this->logger = $logger;
		$this->vaultUtils = $vaultUtils;
		$this->httpAdapter = $chHttpAdapter;
	}

	/**
	 * Retrieve assoc array of CredentialsRequest info
	 *
	 * @return array
	 */
	public function requestCredentials(Array $sessionData) {
	    $this->logger->logInfo(1, "Initiating Credentials Request");
		$data = $this->getCredentialsPayload($this->getMerchantId(), $sessionData);
		$this->logger->logDebug(3, "Credentials Request Payload\n" . json_encode($data));
		$chResponse = $this->httpAdapter->sendRequest($data, self::CREDENTIALS_ENDPOINT);
		return $this->parseChCredentialsResponse($chResponse);
	}


	private function parseChCredentialsResponse($chResponse) {
	    $statusCode = $chResponse->getStatusCode();
        $body = $chResponse->getBody();
        $bodyArray = json_decode($body, true) ?: [];
        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->logError(1, "sessions/create request failure");
            $this->logger->logError(2, 'CommerceHub sessions/create HTTP error code: ' . $statusCode);
            $this->logger->logError(2, 'CommerceHub sessions/create body: ' . json_encode($bodyArray));
            throw new \Exception('CommerceHub sessions/create HTTP error code: ' . $statusCode, 1);
        }
        $data = [
            self::KEY_SESSION_ID => $bodyArray[self::KEY_SESSION][self::KEY_SESSION_ID],
            self::KEY_SDK_DATA   => $bodyArray[self::KEY_CHECKOUT_INTERACTIONS][self::KEY_SDK_DATA],
        ];
        $this->logger->logInfo(1, "sessions/create request success");
        return $data;
    }

	/**
	* Build the request body for POST /ch/v1/sessions/create.
    */
	private function getCredentialsPayload($merchantId, $sessionData) {
	    $intent = $sessionData[self::KEY_SESSION]['intent'] ?? self::INTENT_AUTHORIZE;
        $payload = [
            self::KEY_MERCHANT_DETAILS => [
                self::KEY_MERCHANT_ID => $merchantId, self::KEY_TERMINAL_ID => $this->chConfig->getTerminalId(),],
                self::KEY_SESSION => ['intent' => $intent, 'intentInitiator' => self::INTENT_INITIATOR_MERCHANT,],
                self::KEY_CHECKOUT_INTERACTIONS => ['checkoutType' => self::CHECKOUT_TYPE_SDK, 'expiresInMinutes' => $this->chConfig->getCheckoutInteractionsExpiresInMinutes(),],
            ];
            if (isset($sessionData[self::KEY_AMOUNT])) {
                $payload[self::KEY_AMOUNT] = $sessionData[self::KEY_AMOUNT];
            }
            // orderData and shippingAddress are required by Affirm BNPL sessions/data-capture
            if (isset($sessionData[self::KEY_ORDER_DATA])) {
                $payload[self::KEY_ORDER_DATA] = $sessionData[self::KEY_ORDER_DATA];
            }
            if (isset($sessionData[self::KEY_SHIPPING_ADDRESS])) {
                $payload[self::KEY_SHIPPING_ADDRESS] = $sessionData[self::KEY_SHIPPING_ADDRESS];
            }
            return $payload;
        }

	private function buildPaymentTokenSource(string $tokenData, string $customerId): array {
	    $token = $this->vaultUtils->getByGatewayToken($tokenData, self::CODE_PAYMENT_METHOD_COMMERCEHUB, $customerId);
        if (is_null($token) || count($token) < 1) {
            throw new \Exception("Unable to locate payment token for Commercehub payment session with source.");
        }
        $details = json_decode($token[0]["details"], true);
        if (is_null($details) || !isset($details["expirationDate"])) {
            throw new \Exception("Unable to locate payment token expiration date for Commercehub payment session with source.");
        }
        list($month, $year) = explode('/', $details["expirationDate"]);
        return array(
        "sourceType" => "PaymentToken",
        "tokenData" => $tokenData,
        "tokenSource" => "TRANSARMOR",
        "card" => array("expirationMonth" => $month, "expirationYear" => $year)
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