<?php
/**
 * Order Inquiry Adapter for handling async transaction status checks
 * Used for Affirm and other payment methods that return 202 PROCESSING status
 */
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Logger\MultiLevelLogger;

class OrderInquiryAdapter
{
    const INQUIRY_ENDPOINT_PAYPAL = 'checkouts/v1/inquiry';
    const INQUIRY_ENDPOINT_COMMERCEHUB = 'payments/v1/transaction-inquiry';

    // Transaction states
    const STATE_PROCESSING = 'PROCESSING';
    const STATE_CAPTURED = 'CAPTURED';
    const STATE_AUTHORIZED = 'AUTHORIZED';
    const STATE_DECLINED = 'DECLINED';
    const STATE_VOIDED = 'VOIDED';
    const STATE_REFUNDED = 'REFUNDED';

    // Approval statuses
    const APPROVAL_APPROVED = 'APPROVED';
    const APPROVAL_DECLINED = 'DECLINED';

    /**
     * @var ChHttpAdapter
     */
    private $httpAdapter;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
     * @param ChHttpAdapter $httpAdapter
     * @param Config $config
     * @param MultiLevelLogger $logger
     */
    public function __construct(
        ChHttpAdapter $httpAdapter,
        Config $config,
        MultiLevelLogger $logger
    ) {
        $this->httpAdapter = $httpAdapter;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Execute inquiry request to get transaction status
     *
     * @param string $referenceOrderId The CommerceHub order ID (CHG01...)
     * @param string $orderId The Magento order increment ID for logging
    * @param bool $isOrder Whether this is a PayPal/Affirm order (uses different endpoint). Default: false
     * @return array Response containing transactionState, approvalStatus, and full response
     */
    public function executeInquiry(string $referenceOrderId, string $orderId, bool $isOrder = false): array
    {
        $logIdentifier = "Order ID: " . $orderId;

        $this->logger->logInfo(1, "Initiating order inquiry for reference order: " . $referenceOrderId, $logIdentifier);

        $requestData = $this->buildInquiryRequest($referenceOrderId);
        $endpoint = $isOrder ? self::INQUIRY_ENDPOINT_PAYPAL : self::INQUIRY_ENDPOINT_COMMERCEHUB;

        $this->logger->logDebug(3, "Inquiry Request Body:\n" . json_encode($requestData, JSON_PRETTY_PRINT), $logIdentifier);

        try {
            /** @var ChHttpResponse $response */
            $response = $this->httpAdapter->sendRequest($requestData, $endpoint);

            $statusCode = $response->getStatusCode();
            $responseBody = json_decode($response->getBody(), true);

            $this->logger->logDebug(3, "Inquiry Response Status Code: " . $statusCode, $logIdentifier);
            $this->logger->logDebug(3, "Inquiry Response Body:\n" . json_encode($responseBody, JSON_PRETTY_PRINT), $logIdentifier);

            return $this->parseInquiryResponse($statusCode, $responseBody, $logIdentifier);

        } catch (\Exception $e) {
            $this->logger->logError(2, "Order inquiry failed: " . $e->getMessage(), $logIdentifier);
            return [
                'success' => false,
                'transactionState' => null,
                'approvalStatus' => null,
                'orderStatus' => null,
                'error' => $e->getMessage(),
                'rawResponse' => null
            ];
        }
    }

    /**
     * Build the inquiry request payload
     *
     * @param string $referenceOrderId
     * @return array
     */
    private function buildInquiryRequest(string $referenceOrderId): array
    {
        return [
            'referenceTransactionDetails' => [
                'referenceOrderId' => $referenceOrderId
            ],
            'merchantDetails' => [
                'merchantId' => $this->config->getMerchantId(),
                'terminalId' => $this->config->getTerminalId()
            ]
        ];
    }

    /**
     * Parse the inquiry response and extract relevant status information
     *
     * @param int $statusCode
     * @param array|null $responseBody
     * @param string $logIdentifier
     * @return array
     */
    private function parseInquiryResponse(int $statusCode, ?array $responseBody, string $logIdentifier): array
    {
        $result = [
            'success' => false,
            'transactionState' => null,
            'approvalStatus' => null,
            'orderStatus' => null,
            'transactionType' => null,
            'apiTraceId' => null,
            'transactionId' => null,
            'referenceNumber' => null,
            'approvedAmount' => null,
            'error' => null,
            'rawResponse' => $responseBody
        ];

        // Check for HTTP success
        if (!in_array($statusCode, [200, 201, 202])) {
            $result['error'] = "HTTP error: " . $statusCode;
            $this->logger->logError(2, "Inquiry returned non-success status: " . $statusCode, $logIdentifier);
            return $result;
        }

        if (empty($responseBody)) {
            $result['error'] = "Empty response body";
            $this->logger->logError(2, "Inquiry returned empty response", $logIdentifier);
            return $result;
        }

        // Extract gateway response details (for the inquiry itself)
        if (isset($responseBody['gatewayResponse'])) {
            $gatewayResponse = $responseBody['gatewayResponse'];
            if (isset($gatewayResponse['transactionProcessingDetails'])) {
                $details = $gatewayResponse['transactionProcessingDetails'];
                $result['apiTraceId'] = $details['apiTraceId'] ?? null;
            }
        }

        // Extract order status - this is the key indicator for Order API
        if (isset($responseBody['order']['orderStatus'])) {
            $result['orderStatus'] = $responseBody['order']['orderStatus'];
        }

        // Parse the transactions array to find the actual transaction states
        // Look for CAPTURED or AUTHORIZED transactions
        $hasProcessingCapture = false;
        if (isset($responseBody['transactions']) && is_array($responseBody['transactions'])) {
            // First pass: check if there's a PROCESSING capture transaction
            foreach ($responseBody['transactions'] as $transaction) {
                $txnState = $transaction['gatewayResponse']['transactionState'] ?? null;
                $txnType = $transaction['gatewayResponse']['transactionType'] ?? null;
                if ($txnType === 'ORDER_CAPTURE' && $txnState === 'PROCESSING') {
                    $hasProcessingCapture = true;
                    break;
                }
            }
            
            // Second pass: parse transaction states
            foreach ($responseBody['transactions'] as $transaction) {
                $txnState = $transaction['gatewayResponse']['transactionState'] ?? null;
                $txnType = $transaction['gatewayResponse']['transactionType'] ?? null;
                $txnId = $transaction['gatewayResponse']['transactionProcessingDetails']['transactionId'] ?? null;

                // Prioritize CAPTURED over AUTHORIZED
                if ($txnState === self::STATE_CAPTURED) {
                    $result['transactionState'] = self::STATE_CAPTURED;
                    $result['transactionType'] = $txnType;
                    $result['transactionId'] = $txnId;

                    // Get payment receipt details
                    if (isset($transaction['paymentReceipt'])) {
                        $paymentReceipt = $transaction['paymentReceipt'];
                        if (isset($paymentReceipt['approvedAmount'])) {
                            $result['approvedAmount'] = $paymentReceipt['approvedAmount'];
                        }
                        if (isset($paymentReceipt['processorResponseDetails']['referenceNumber'])) {
                            $result['referenceNumber'] = $paymentReceipt['processorResponseDetails']['referenceNumber'];
                        }
                    }
                    break; // Found CAPTURED, no need to continue
                } elseif ($txnState === self::STATE_AUTHORIZED && $result['transactionState'] !== self::STATE_CAPTURED) {
                    // Skip AUTHORIZED if there's a PROCESSING capture - we need to wait for capture to complete
                    if ($hasProcessingCapture) {
                        continue;
                    }
                    $result['transactionState'] = self::STATE_AUTHORIZED;
                    $result['transactionType'] = $txnType;
                    $result['transactionId'] = $txnId;

                    if (isset($transaction['paymentReceipt'])) {
                        $paymentReceipt = $transaction['paymentReceipt'];
                        if (isset($paymentReceipt['approvedAmount'])) {
                            $result['approvedAmount'] = $paymentReceipt['approvedAmount'];
                        }
                        if (isset($paymentReceipt['processorResponseDetails']['referenceNumber'])) {
                            $result['referenceNumber'] = $paymentReceipt['processorResponseDetails']['referenceNumber'];
                        }
                    }
                } elseif ($txnState === self::STATE_DECLINED || $txnState === self::STATE_VOIDED) {
                    $result['transactionState'] = $txnState;
                    $result['transactionType'] = $txnType;
                    $result['transactionId'] = $txnId;
                    break; // Found failed state, stop
                }
            }
        }

        // If no transaction state found, check orderStatus only if there's NO processing capture
        if ($result['transactionState'] === null && $result['orderStatus'] === 'COMPLETED' && !$hasProcessingCapture) {
            // Order completed and no processing capture - treat as captured
            $result['transactionState'] = self::STATE_CAPTURED;
            $this->logger->logInfo(1, 'Order status is COMPLETED, treating as CAPTURED', $logIdentifier);
        } elseif ($hasProcessingCapture && $result['transactionState'] === null) {
            // Has a PROCESSING capture but no final state yet - keep polling
            $result['transactionState'] = null;
            $this->logger->logInfo(1, 'Capture is PROCESSING - will retry inquiry', $logIdentifier);
        }

        // Determine success based on transaction state (not just order status)
        $successStates = [self::STATE_CAPTURED, self::STATE_AUTHORIZED];
        $result['success'] = in_array($result['transactionState'], $successStates);
        
        // If capture is still processing, it's not a success yet
        if ($hasProcessingCapture && $result['transactionState'] !== self::STATE_CAPTURED) {
            $result['success'] = false;
        }

        // Set approval status only for successful transactions
        if ($result['success'] && $result['approvalStatus'] === null) {
            $result['approvalStatus'] = self::APPROVAL_APPROVED;
        }
        
        // If not successful, clear approval status
        if (!$result['success']) {
            $result['approvalStatus'] = null;
        }

        $this->logger->logInfo(1, sprintf(
            "Inquiry result - State: %s, Approval: %s, Order Status: %s, Success: %s",
            $result['transactionState'] ?? 'N/A',
            $result['approvalStatus'] ?? 'N/A',
            $result['orderStatus'] ?? 'N/A',
            $result['success'] ? 'Yes' : 'No'
        ), $logIdentifier);

        return $result;
    }

    /**
     * Check if the transaction state indicates we should retry the inquiry
     *
     * @param string|null $transactionState
     * @return bool
     */
    public function shouldRetry(?string $transactionState): bool
    {
        return $transactionState === self::STATE_PROCESSING || $transactionState === null;
    }

    /**
     * Check if the transaction was successful (captured or authorized)
     *
     * @param array $inquiryResult
     * @return bool
     */
    public function isTransactionSuccessful(array $inquiryResult): bool
    {
        $successStates = [self::STATE_CAPTURED, self::STATE_AUTHORIZED];

        // Check transaction state
        if (in_array($inquiryResult['transactionState'], $successStates)) {
            return true;
        }

        // Also check order status for Order API responses
        if (isset($inquiryResult['orderStatus']) && $inquiryResult['orderStatus'] === 'COMPLETED') {
            return true;
        }

        return false;
    }

    /**
     * Check if the transaction was declined or failed
     *
     * @param array $inquiryResult
     * @return bool
     */
    public function isTransactionFailed(array $inquiryResult): bool
    {
        $failedStates = [self::STATE_DECLINED, self::STATE_VOIDED];

        return in_array($inquiryResult['transactionState'], $failedStates)
            || $inquiryResult['approvalStatus'] === self::APPROVAL_DECLINED;
    }

    /**
     * Void/cancel the transaction in CommerceHub
     *
     * @param string $referenceOrderId The CommerceHub order ID
     * @param string $orderId The Magento order increment ID for logging
    * @param bool $isOrder Whether this is a PayPal/Affirm order. Default: false
     * @return array Response array
     */
    public function voidTransaction(string $referenceOrderId, string $orderId, bool $isOrder = false): array
    {
        $logIdentifier = "Order ID: " . $orderId;

        $this->logger->logInfo(1, "Initiating transaction void for reference order: " . $referenceOrderId, $logIdentifier);

        $requestData = [
            'referenceTransactionDetails' => [
                'referenceOrderId' => $referenceOrderId
            ],
            'merchantDetails' => [
                'merchantId' => $this->config->getMerchantId(),
                'terminalId' => $this->config->getTerminalId()
            ]
        ];

        $endpoint = 'payments/v1/cancel';

        $this->logger->logDebug(3, "Void Request Body:\n" . json_encode($requestData, JSON_PRETTY_PRINT), $logIdentifier);

        try {
            /** @var ChHttpResponse $response */
            $response = $this->httpAdapter->sendRequest($requestData, $endpoint);

            $statusCode = $response->getStatusCode();
            $responseBody = json_decode($response->getBody(), true);

            $this->logger->logDebug(3, "Void Response Status Code: " . $statusCode, $logIdentifier);
            $this->logger->logDebug(3, "Void Response Body:\n" . json_encode($responseBody, JSON_PRETTY_PRINT), $logIdentifier);

            if (in_array($statusCode, [200, 201])) {
                $this->logger->logInfo(1, "Transaction successfully voided in CommerceHub", $logIdentifier);
                return [
                    'success' => true,
                    'statusCode' => $statusCode,
                    'rawResponse' => $responseBody
                ];
            } else {
                $this->logger->logError(2, "Void request returned status: " . $statusCode, $logIdentifier);
                return [
                    'success' => false,
                    'statusCode' => $statusCode,
                    'rawResponse' => $responseBody
                ];
            }

        } catch (\Exception $e) {
            $this->logger->logError(2, "Transaction void failed: " . $e->getMessage(), $logIdentifier);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'rawResponse' => null
            ];
        }
    }
}
