<?php
/**
 * Validates async Sale transactions for Affirm
 * Accepts HTTP 202 and PROCESSING state as valid (will be resolved via inquiry job)
 */
namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Validator\CommerceHub\TransactionResponseValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Service\CommerceHub\FailedTransactionManager;

class AsyncSaleResponseValidator extends TransactionResponseValidator
{
    const HTTP_ACCEPTED = 202;
    const STATE_PROCESSING = 'PROCESSING';
    const PAYMENT_ACTION = 'SALE';

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param SubjectReader $subjectReader
     * @param MultiLevelLogger $logger
     * @param FailedTransactionManager $failedTxnManager
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        SubjectReader $subjectReader,
        MultiLevelLogger $logger,
        FailedTransactionManager $failedTxnManager
    ) {
        parent::__construct($resultFactory, $subjectReader, $logger, $failedTxnManager);
        
        // Accept CAPTURED and PROCESSING as success states
        array_push($this->successStates, self::STATE_CAPTURE, self::STATE_PROCESSING);
        // Accept HTTP 202 as success status
        array_push($this->successStatuses, self::HTTP_ACCEPTED);
    }

    /**
     * Validate the response - accept PROCESSING/202 for async handling
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $chRawResponse = $this->subjectReader->readChResponseFromResponse($validationSubject);
        $order = $this->subjectReader->readPayment($validationSubject)->getOrder();
        $orderIncrementId = $order->getOrderIncrementId();

        $statusCode = $chRawResponse[HttpClient::STATUS_CODE_KEY] ?? 0;
        $transactionState = $chRawResponse[HttpClient::RESPONSE_KEY]['gatewayResponse']['transactionState'] ?? null;

        // Check for async PROCESSING response (202 or PROCESSING state)
        if ($statusCode == self::HTTP_ACCEPTED || $transactionState === self::STATE_PROCESSING) {
            $this->logger->logInfo(1, sprintf(
                'Async Sale transaction (Status: %d, State: %s). Inquiry job will be queued.',
                $statusCode,
                $transactionState ?? 'N/A'
            ), 'Order ID: ' . $orderIncrementId);

            return $this->createResult(true);
        }

        // For non-async responses, use standard validation
        return parent::validate($validationSubject);
    }

    /**
     * Get payment action type
     *
     * @return string
     */
    protected function getPaymentAction(): string
    {
        return self::PAYMENT_ACTION;
    }
}
