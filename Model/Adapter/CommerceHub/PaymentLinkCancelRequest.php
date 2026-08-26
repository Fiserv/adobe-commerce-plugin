<?php
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Lib\CommerceHub\Model\CancelRequest;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\ReferenceTransactionDetails;

class PaymentLinkCancelRequest
{
    const CANCEL_ENDPOINT = 'checkouts/v1/payment-link';
    const KEY_OPERATION_TYPE = "CANCEL";
    const KEY_RESPONSE = "response";
    const KEY_STATUS_CODE = "statusCode";

    /**
     * @var Config
     */
    private $chConfig;

    /**
     * @var ChHttpAdapter
     */
    private $httpAdapter;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param Config $config
     * @param ChHttpAdapter $httpAdapter
     * @param MultiLevelLogger $logger
     */
    public function __construct(
        Config $config,
        ChHttpAdapter $httpAdapter,
        MultiLevelLogger $logger
    ) {
        $this->chConfig = $config;
        $this->httpAdapter = $httpAdapter;
        $this->logger = $logger;
    }

    /**
     * Cancel a payment link via API
     *
     * @param string $merchantId
     * @param string $terminalId
     * @param string $referenceTransactionId
     * @return array
     * @throws \Exception
     */
    public function cancelPaymentLink($merchantId, $terminalId, $referenceTransactionId)
    {
        $this->logger->logInfo(
            1,
            "Initiating payment link cancellation",
            "Reference Transaction ID: {$referenceTransactionId}"
        );

        // Build the cancel request payload
        $payload = $this->buildCancelPayload($merchantId, $terminalId, $referenceTransactionId);

        $this->logger->logDebug(3, "Payment Link Cancel Request Payload:\n" . json_encode($payload, JSON_PRETTY_PRINT));

        // Send the API request
        $chResponse = $this->httpAdapter->sendRequest($payload, self::CANCEL_ENDPOINT);
        
        return $this->parseCancelResponse($chResponse, $referenceTransactionId);
    }

    /**
     * Build the cancel request payload
     *
     * @param string $merchantId
     * @param string $terminalId
     * @param string $referenceTransactionId
     * @return CancelRequest
     */
    private function buildCancelPayload($merchantId, $terminalId, $referenceTransactionId)
    {
        $req = new CancelRequest();

        // Set transaction details with CANCEL operation type
        $txnDetails = new TransactionDetails();
        $txnDetails->setOperationType(self::KEY_OPERATION_TYPE);
        $req->setTransactionDetails($txnDetails);

        // Set merchant details
        $merchantDetails = new MerchantDetails();
        $merchantDetails->setMerchantId($merchantId);
        $merchantDetails->setTerminalId($terminalId);
        $req->setMerchantDetails($merchantDetails);

        // Set reference transaction details
        $refTxnDetails = new ReferenceTransactionDetails();
        $refTxnDetails->setReferenceTransactionId($referenceTransactionId);
        $req->setReferenceTransactionDetails($refTxnDetails);

        return $req;
    }

    /**
     * Parse the cancel response
     *
     * @param object $chResponse
     * @param string $referenceTransactionId
     * @return array
     * @throws \Exception
     */
    private function parseCancelResponse($chResponse, $referenceTransactionId)
    {
        $statusCode = $chResponse->getStatusCode();
        $response = $chResponse->getResponse();
        $body = $chResponse->getBody();

        $this->logger->logInfo(
            1,
            "Payment link cancellation response received",
            "Status Code: {$statusCode}, Reference Transaction ID: {$referenceTransactionId}"
        );
        $this->logger->logDebug(3, "Response Body:\n" . print_r($body, true));

        // Check for success status codes
        if (in_array($statusCode, [200, 201, 202])) {
            $this->logger->logInfo(1, "Payment link cancelled successfully");
            return [
                self::KEY_STATUS_CODE => $statusCode,
                self::KEY_RESPONSE => $body
            ];
        } else {
            $errorMsg = "Payment link cancellation failed with status code {$statusCode}";
            $this->logger->logError(1, $errorMsg);
            throw new \Exception($errorMsg);
        }
    }
}
