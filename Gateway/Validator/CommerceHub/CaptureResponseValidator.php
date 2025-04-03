<?php
namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Validator\CommerceHub\TransactionResponseValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Service\CommerceHub\FailedTransactionManager;

/**
 * Validates the status of an attempted Capture transaction
 */
class CaptureResponseValidator extends TransactionResponseValidator
{

	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 * @param MultiLevelLogger $logger
	 * @param FailedTransactionRepositoryInterface $failedTransactionRepository
	 * @param FailedTransactionFactory $failedTransactionFactory
	 * @param FailedTransactionResource $failedTransactionResource
	 */
	public function __construct(
		ResultInterfaceFactory $resultFactory, 
		SubjectReader $subjectReader, 
		MultiLevelLogger $logger, 
		FailedTransactionManager $failedTxnManager
	) {
        parent::__construct(
			$resultFactory, 
			$subjectReader, 
			$logger, 
			$failedTxnManager
		); 	
		array_push($this->successStates, self::STATE_CAPTURE);
	}
}
