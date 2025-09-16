<?php
namespace Fiserv\Payments\Gateway\Validator\PayPal;

use Fiserv\Payments\Gateway\Subject\PayPal\SubjectReader;
use Fiserv\Payments\Gateway\Http\PayPal\Client\HttpClient;
use Fiserv\Payments\Gateway\Validator\PayPal\TransactionResponseValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Service\PayPal\FailedTransactionManager;

/**
 * Validates the status of an attempted Capture transaction
 */
class CaptureResponseValidator extends TransactionResponseValidator
{
    const PAYMENT_ACTION = "CAPTURE";

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param SubjectReader $subjectReader
     * @param MultiLevelLogger $logger
     * @param FailedTransactionManager $failedTransactionManager
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        SubjectReader $subjectReader,
        MultiLevelLogger $logger,
        FailedTransactionManager $failedTransactionManager
    ) {
        parent::__construct($resultFactory, $subjectReader, $logger, $failedTransactionManager);
        array_push($this->successStates, self::STATE_CAPTURE);
    }

    public function validate(array $validationSubject): ResultInterface
    {
        $paypalResponse = $this->subjectReader->readPayPalResponse($validationSubject);
        $errorMessages = [];
        $errorCodes = [];

        // Verify Status Code
        $statusCode = $this->subjectReader->getValueSafely($paypalResponse, HttpClient::STATUS_CODE_KEY, $this->paths[HttpClient::STATUS_CODE_KEY]);
        if (!$this->isStatusSuccessful($statusCode)) {
            array_push($errorMessages, "PayPal capture failed: Invalid HTTP status code " . $statusCode);
            array_push($errorCodes, $statusCode);
            $this->logger->logError(2, "Transaction failure. PayPal response returned with unsuccessful status", "Status Code: " . $statusCode);
            return $this->createResult(false, $errorMessages, $errorCodes);
        }

        // Extract transaction state
        $transactionState = $this->subjectReader->getValueSafely($paypalResponse, 'transactionState', $this->paths['transactionState']);
        if (!$this->isStateSuccessful($transactionState)) {
            array_push($errorMessages, "PayPal capture failed: Transaction state is " . ($transactionState ?? "unknown"));
            array_push($errorCodes, $transactionState);
            $this->logger->logError(2, "Transaction failure. PayPal response returned with unsuccessful transaction state: " . $transactionState);
            return $this->createResult(false, $errorMessages, $errorCodes);
        }

        $this->logger->logInfo(1, "Transaction success", "Transaction ID: " . $this->subjectReader->getValueSafely($paypalResponse, 'transactionId', $this->paths['transactionId']));
        return $this->createResult(true);
    }

    protected function getPaymentAction()
    {
        return self::PAYMENT_ACTION;
    }
}
