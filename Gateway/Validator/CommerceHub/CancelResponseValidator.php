<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Validator\CommerceHub\TransactionResponseValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Service\CommerceHub\FailedTransactionManager;

/**
 * Validates the status of an attempted Cancel transaction
 */
class CancelResponseValidator extends TransactionResponseValidator
{
	const PAYMENT_ACTION = "VOID";

	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(
		ResultInterfaceFactory $resultFactory, 
		SubjectReader $subjectReader, 
		MultiLevelLogger $logger, 
		FailedTransactionManager $failedTxnManager
	){
		parent::__construct(
			$resultFactory, 
			$subjectReader, 
			$logger, 
			$failedTxnManager
		);
		array_push($this->successStates, self::STATE_VOIDED);
	}

	protected function getPaymentAction()
	{
		return self::PAYMENT_ACTION;
	}
}
