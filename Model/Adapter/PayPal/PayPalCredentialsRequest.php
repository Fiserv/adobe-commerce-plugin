<?php
namespace Fiserv\Payments\Model\Adapter\PayPal;

use Fiserv\Payments\Gateway\Config\PayPal\Config;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Fiserv\Payments\Model\Adapter\PayPal\PayPalHttpAdapter;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\System\Utils\PayPal\VaultPaymentTokenUtils;

class PayPalCredentialsRequest
{
    const CREDENTIALS_ENDPOINT = 'payments-vas/v1/security/credentials';

    // PayPal Credentials Request Keys
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
    const KEY_ADDITIONAL_DATA = "additionalData";
    const KEY_ECOM_URL = "ecomUrl";

    const CODE_PAYMENT_METHOD = "fiserv_paypal";

    // PayPal Credentials Response Keys
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
    private $payPalConfig;
    private $commerceHubConfig;

    private $vaultUtils;

    /**
     * @PayPalHttpAdapter
     */
    private $paypalHttpAdapter;

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
        CommerceHubConfig $commerceHubConfig,
        PayPalHttpAdapter $paypalHttpAdapter,
        StoreManagerInterface $storeManager,
        MultiLevelLogger $logger,
        VaultPaymentTokenUtils $vaultUtils
    ) {
        $this->payPalConfig = $config;
        $this->commerceHubConfig = $commerceHubConfig;
        $this->paypalHttpAdapter = $paypalHttpAdapter;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->vaultUtils = $vaultUtils;
    }

    /**
     * Retrieve assoc array of PayPal CredentialsRequest info
     *
     * @return array
     */
    public function requestCredentials(Array $sessionData)
    {
        $this->logger->logInfo(1, "Initiating PayPal Credentials Request");
        $merchantId = $this->getMerchantId();
        $this->logger->logInfo(2, "Merchant ID used: " . var_export($merchantId, true));
        $this->logger->logInfo(2, "Session Data: " . var_export($sessionData, true));
           $data = $this->getCredentialsPayload($merchantId, $sessionData);
           $this->logger->logInfo(2, "Payload sent to CommerceHub: " . var_export($data, true));

           // Validate required fields before sending request
           $missingFields = [];
           if (empty($data[self::KEY_DOMAINS])) {
               $missingFields[] = self::KEY_DOMAINS;
           }
           if (empty($data[self::KEY_MERCHANT_DETAILS][self::KEY_MERCHANT_ID])) {
               $missingFields[] = self::KEY_MERCHANT_ID;
           }
           // Add more required fields as needed
           if (!empty($missingFields)) {
               $errorMsg = 'Missing required fields in PayPal credentials payload: ' . implode(', ', $missingFields);
               $this->logger->logError(1, $errorMsg);
               throw new \Exception($errorMsg);
           }

           try {
               $payPalResponse = $this->paypalHttpAdapter->sendRequest($data, self::CREDENTIALS_ENDPOINT);
               $this->logger->logInfo(2, "Raw response from CommerceHub: " . var_export($payPalResponse, true));
               return $this->parsePayPalCredentialsResponse($payPalResponse);
           } catch (\Exception $e) {
               $this->logger->logError(1, "Exception in sendRequest: " . $e->getMessage());
               $this->logger->logError(2, $e->getTraceAsString());
               throw $e;
           }
    }

    private function parsePayPalCredentialsResponse($payPalResponse) {
        $this->logger->logInfo(2, "Parsing PayPal credentials response");
        $statusCode = $payPalResponse->getStatusCode();
        $response = $payPalResponse->getResponse();
        $headerLength = $payPalResponse->getHeaderLength();
        $body = $payPalResponse->getBody();

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
            // Add customerId if available in sessionData, else fallback to sessionId or a placeholder
            if (isset($sessionData[self::KEY_CUSTOMER][self::KEY_CUSTOMER_ID])) {
                $data['customerId'] = $sessionData[self::KEY_CUSTOMER][self::KEY_CUSTOMER_ID];
            } else if (isset($data['id'])) {
                $data['customerId'] = $data['id'];
            } else if (isset($data['sessionId'])) {
                $data['customerId'] = $data['sessionId'];
            } else {
                $data['customerId'] = 'guest';
            }
            $this->logger->logInfo(1, "PayPal credentials request success");
            
        } else {
            $this->logger->logError(1, "PayPal credentials request failure");
            $this->logger->logError(2, 'PayPal credentials request HTTP error code: ' . $statusCode);
            $this->logger->logError(2, 'PayPal credentials request body: ' . json_encode($bodyArray));
            throw new \Exception('PayPal credentials request HTTP error code: ' . $statusCode, 1);
        }
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
        

        $payload[self::KEY_ADDITIONAL_DATA_COMMON] = array(
            self::KEY_ADDITIONAL_DATA => array(
                self::KEY_ECOM_URL => $this->getStoreBaseUrl() 
            )
        );

        return $payload;
    }

    private function buildPaymentTokenSource(string $tokenData, string $customerId) : array
    {
        $token = $this->vaultUtils->getByGatewayToken($tokenData, self::CODE_PAYMENT_METHOD, $customerId);
        if (is_null($token) || count($token) < 1) 
        {
            throw new \Exception("Unable to locate payment token for PayPal payment session with source.");
        }

        $details = json_decode($token[0]["details"], true);
        if (is_null($details) || !isset($details["expirationDate"]))
        {   
            throw new \Exception("Unable to locate payment token expiration date for PayPal payment session with source.");
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
        return $this->commerceHubConfig->getMerchantId();
    }
}
