<?php
namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Validator\CommerceHub\TransactionResponseValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Service\CommerceHub\FailedTransactionManager;

/**
 * Validates the status of an attempted Sale transaction
 */
class SaleResponseValidator extends TransactionResponseValidator
{

	const PAYMENT_ACTION = "SALE";

	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 * @param MultiLevelLogger $logger
	 * @param FailedTransactionRepositoryInterface $failedTransactionRepository
	 * @param FailedTransactionFactory $failedTransactionFactory
	 * @param FailedTransaction $failedTransactionResource
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

	protected function getPaymentAction()
	{
		return self::PAYMENT_ACTION;
	}
}
