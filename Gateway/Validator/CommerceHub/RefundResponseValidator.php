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
use Fiserv\Payments\Model\FailedTransactionFactory;
use Fiserv\Payments\Model\ResourceModel\FailedTransaction;
use Fiserv\Payments\Api\FailedTransaction\FailedTransactionRepositoryInterface;

/**
 * Validates the status of an attempted Refund transaction
 */
class RefundResponseValidator extends TransactionResponseValidator
{	
	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(ResultInterfaceFactory $resultFactory, SubjectReader $subjectReader, MultiLevelLogger $logger,FailedTransactionRepositoryInterface $failedTransactionRepository, FailedTransactionFactory $failedTransactionFactory, FailedTransaction $failedTransactionResource)
	{
		parent::__construct($resultFactory, $subjectReader, $logger,$failedTransactionRepository, $failedTransactionFactory, $failedTransactionResource);
		array_push($this->successStates, self::STATE_CAPTURE);
	}

}