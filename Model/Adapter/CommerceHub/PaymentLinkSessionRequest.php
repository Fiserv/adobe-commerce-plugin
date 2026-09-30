<?php
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Gateway\Config\PayByLink\Config as PblConfig;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\PayByLink\PaymentLinkSessionException;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\Amount;
use Fiserv\Payments\Lib\CommerceHub\Model\Session;
use Fiserv\Payments\Lib\CommerceHub\Model\CheckoutInteractions;
use Fiserv\Payments\Lib\CommerceHub\Model\ReturnUrls;
use Magento\Framework\Url as FrontendUrl;
use Magento\Payment\Model\MethodInterface;

class PaymentLinkSessionRequest
{
    const SESSION_CREATE_ENDPOINT = 'v1/sessions/create';
    const KEY_RESPONSE = "response";
    const KEY_STATUS_CODE = "statusCode";

    /**
     * @var Config
     */
    private $chConfig;

    /**
     * @var PblConfig
     */
    private $pblConfig;

    /**
     * @var ChHttpAdapter
     */
    private $httpAdapter;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
     * Frontend URL builder.
     *
     * Injected as the concrete Magento\Framework\Url (not UrlInterface) so that
     * the return URLs are always frontend-scoped. In the admin order-creation
     * flow UrlInterface resolves to Magento\Backend\Model\Url, which would build
     * an admin-scoped URL and cause a 404 when the shopper is redirected back
     * after paying.
     *
     * @var FrontendUrl
     */
    private $urlBuilder;

    public function __construct(
        Config $chConfig,
        PblConfig $pblConfig,
        ChHttpAdapter $httpAdapter,
        MultiLevelLogger $logger,
        FrontendUrl $urlBuilder
    ) {
        $this->chConfig = $chConfig;
        $this->pblConfig = $pblConfig;
        $this->httpAdapter = $httpAdapter;
        $this->logger = $logger;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Create a payment link session via Commerce Hub API
     *
     * @param float $total
     * @param string $currency
     * @param int $expiresInMinutes
     * @param string|null $pageId
     * @param array $orderData Additional order data (billingAddress, customer, etc.)
     * @param string|null $orderId Order increment ID for return URL identification
     * @param string $paymentAction
     * @param int|null $storeId Store the order belongs to (used to scope the return URLs)
     * @param string $note Optional note shown as the payment description
     * @return array
     * @throws \Exception
     */
    public function createSession($total, $currency, $expiresInMinutes, $pageId = null, $orderData = [], $orderId = null, $paymentAction = MethodInterface::ACTION_AUTHORIZE, $storeId = null, $note = '')
    {
        $this->logger->logInfo(1, "Initiating Pay by Link session creation");

        $payload = $this->buildSessionPayload($total, $currency, $expiresInMinutes, $pageId, $orderData, $orderId, $paymentAction, $storeId, $note);

        $this->logger->logDebug(3,"Pay by Link Session Create Payload:\n" . json_encode($payload, JSON_PRETTY_PRINT));

        $chResponse = $this->httpAdapter->sendRequest($payload, self::SESSION_CREATE_ENDPOINT);

        return $this->parseSessionResponse($chResponse, $payload);
    }

    /**
     * Build the session create request payload
     *
     * @param float $total
     * @param string $currency
     * @param int $expiresInMinutes
     * @param string|null $pageId
     * @param array $orderData
     * @param string|null $orderId
     * @param string $paymentAction
     * @param int|null $storeId
    * @param string $note
     * @return array
     */
    private function buildSessionPayload($total, $currency, $expiresInMinutes, $pageId, $orderData = [], $orderId = null, $paymentAction = MethodInterface::ACTION_AUTHORIZE, $storeId = null, $note = '')
    {
        $urlParams = ['_secure' => true, '_nosid' => true];
        if ($orderId) {
            $urlParams['pbl_order_ref'] = $orderId;
        }
        $completionUrl = $this->buildReturnUrl('fiserv/paybylink/complete', $urlParams, $storeId);
        $cancelUrl = $this->buildReturnUrl('fiserv/paybylink/cancel', $urlParams, $storeId);

        $merchantDetails = new MerchantDetails();
        $merchantDetails->setMerchantId($this->chConfig->getMerchantId());
        $merchantDetails->setTerminalId($this->chConfig->getTerminalId());

        $transactionDetails = new TransactionDetails();
        $transactionDetails->setCaptureFlag($paymentAction === MethodInterface::ACTION_AUTHORIZE_CAPTURE);
        $note = trim((string) $note);
        if ($note !== '') {
            $transactionDetails->setPaymentDescription($note);
        }

        $amount = new Amount();
        $amount->setTotal(round((float) $total, 2));
        $amount->setCurrency($currency);

        $session = new Session();
        $session->setIntent($paymentAction === MethodInterface::ACTION_AUTHORIZE_CAPTURE ? 'CAPTURE' : 'AUTHORIZE');
        $session->setIntentInitiator('GATEWAY');

        $returnUrls = new ReturnUrls();
        $returnUrls->setCompletionUrl($completionUrl);
        $returnUrls->setCancelUrl($cancelUrl);

        $checkoutInteractions = new CheckoutInteractions();
        $checkoutInteractions->setCheckoutType('URL');
        $checkoutInteractions->setPageId(!empty($pageId) ? $pageId : '');
        $checkoutInteractions->setExpiresInMinutes((int) $expiresInMinutes);
        $checkoutInteractions->setReturnUrls($returnUrls);

        $payload = [
            'merchantDetails' => $merchantDetails,
            'session' => $session,
            'checkoutInteractions' => $checkoutInteractions,
            'transactionDetails' => $transactionDetails,
            'amount' => $amount,
        ];

        // Add billing address if available
        if (!empty($orderData['billingAddress'])) {
            $payload['billingAddress'] = $orderData['billingAddress'];
        }

        // Add shipping address if available
        if (!empty($orderData['shippingAddress'])) {
            $payload['shippingAddress'] = $orderData['shippingAddress'];
        }

        // Add customer info if available
        if (!empty($orderData['customer'])) {
            $payload['customer'] = $orderData['customer'];
        }

        // Add order item details if available
        if (!empty($orderData['orderData'])) {
            $payload['orderData'] = $orderData['orderData'];
        }

        return $payload;
    }

    /**
     * Build a frontend-scoped return URL.
     *
     * The session may be created from the storefront checkout observer or from
     * the admin order-create / retry flow. In the admin area the URL builder's
     * current scope is the admin store, so the frontend route ("fiserv") cannot
     * be resolved against it. Forcing the scope to the order's store guarantees
     * the URL is always built against that store's frontend base URL and route
     * configuration, regardless of the area the request runs in.
     *
     * @param string $routePath
     * @param array $urlParams
     * @param int|null $storeId
     * @return string
     */
    private function buildReturnUrl($routePath, array $urlParams, $storeId = null)
    {
        if ($storeId === null) {
            return $this->urlBuilder->getUrl($routePath, $urlParams);
        }

        $urlParams['_scope'] = $storeId;
        $urlParams['_scope_to_url'] = false;

        return $this->urlBuilder->getUrl($routePath, $urlParams);
    }

    /**
     * Parse the session create response
     *
     * @param object $chResponse
     * @return array
     * @throws \Exception
     */
    private function parseSessionResponse($chResponse, array $requestPayload = [])
    {
        $statusCode = $chResponse->getStatusCode();
        $body = $chResponse->getBody();
        $responseArray = json_decode($body, true);

        $this->logger->logDebug(
            3,
            "Pay by Link Session Create Response (HTTP {$statusCode}):\n" . json_encode($responseArray, JSON_PRETTY_PRINT)
        );

        if ($statusCode >= 200 && $statusCode < 300 && is_array($responseArray)) {
            $this->logger->logInfo(1, "Pay by Link session created successfully");
            return [
                self::KEY_STATUS_CODE => $statusCode,
                self::KEY_RESPONSE => $responseArray,
            ];
        }

        $failureType = $this->resolveFailureTypeByStatusCode($statusCode);
        $errorCode = $this->extractErrorCode($responseArray, $statusCode);
        $errorMessage = $this->extractErrorMessage($responseArray);

        $failureLogContext = [
            'timestamp' => gmdate('c'),
            'endpoint' => self::SESSION_CREATE_ENDPOINT,
            'merchant_id' => $requestPayload['merchantDetails']['merchantId'] ?? '',
            'terminal_id' => $requestPayload['merchantDetails']['terminalId'] ?? '',
            'error_code' => $errorCode,
            'failure_type' => $failureType,
            'http_status' => $statusCode,
            'request_payload' => $requestPayload,
            'response_body' => is_array($responseArray) ? $responseArray : [],
            'raw_response' => is_string($body) ? substr($body, 0, 2000) : '',
        ];

        $this->logger->logError(
            1,
            "Pay by Link session creation failed",
            json_encode($failureLogContext, JSON_UNESCAPED_SLASHES)
        );

        throw new PaymentLinkSessionException(
            "Pay by Link session creation failed. HTTP code: {$statusCode}. Error: {$errorMessage}",
            (int) $statusCode,
            $errorCode,
            $failureType,
            is_array($responseArray) ? $responseArray : []
        );
    }

    /**
     * @param int $statusCode
     * @return string
     */
    private function resolveFailureTypeByStatusCode($statusCode)
    {
        if ($statusCode === 401 || $statusCode === 403) {
            return 'authentication';
        }
        if ($statusCode >= 400 && $statusCode < 500) {
            return 'invalid_input';
        }
        if ($statusCode >= 500) {
            return 'server_error';
        }
        return 'unknown';
    }

    /**
     * @param array|null $responseArray
     * @param int $statusCode
     * @return string
     */
    private function extractErrorCode($responseArray, $statusCode)
    {
        if (!is_array($responseArray)) {
            return 'HTTP_' . (int) $statusCode;
        }

        $candidate = $this->findNestedValueByKeys(
            $responseArray,
            ['errorCode', 'code', 'responseCode', 'reasonCode']
        );
        if (is_scalar($candidate) && (string) $candidate !== '') {
            return strtoupper((string) $candidate);
        }

        return 'HTTP_' . (int) $statusCode;
    }

    /**
     * @param array|null $responseArray
     * @return string
     */
    private function extractErrorMessage($responseArray)
    {
        if (!is_array($responseArray)) {
            return 'Unknown API error';
        }

        $candidate = $this->findNestedValueByKeys(
            $responseArray,
            ['errorMessage', 'message', 'detail', 'details', 'description']
        );
        if (is_scalar($candidate) && trim((string) $candidate) !== '') {
            return trim((string) $candidate);
        }

        return 'Unknown API error';
    }

    /**
     * @param array $payload
     * @param array $keys
     * @return mixed|null
     */
    private function findNestedValueByKeys(array $payload, array $keys)
    {
        $keyMap = [];
        foreach ($keys as $key) {
            $keyMap[strtolower($key)] = true;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($payload));
        foreach ($iterator as $value) {
            $currentKey = $iterator->key();
            if (is_string($currentKey) && isset($keyMap[strtolower($currentKey)])) {
                return $value;
            }
        }

        return null;
    }

}
