<?php
/**
 * Validates async Capture/Settle responses for Affirm
 * Extends standard CaptureResponseValidator to also accept HTTP 202 and PROCESSING state
 */
namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Service\CommerceHub\FailedTransactionManager;

class AsyncCaptureResponseValidator extends CaptureResponseValidator
{
    const HTTP_ACCEPTED = 202;
    const STATE_PROCESSING = 'PROCESSING';

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
        
        // Accept PROCESSING state and HTTP 202 for async handling
        array_push($this->successStates, self::STATE_PROCESSING);
        array_push($this->successStatuses, self::HTTP_ACCEPTED);
    }
}
