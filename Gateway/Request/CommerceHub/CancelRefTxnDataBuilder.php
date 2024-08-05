<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use \Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use \Fiserv\Payments\Lib\CommerceHub\Model\ReferenceTransactionDetails;
use \Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Cancel Reference Transaction Data Builder
 */
class CancelRefTxnDataBuilder implements BuilderInterface
{
	const REF_TXN_KEY = "referenceTransaction";

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(SubjectReader $subjectReader)
	{
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();


		$authTransaction = $payment->getAuthorizationTransaction();
		if ($authTransaction == null) 
		{
			throw new Exception("Unable to locate auth transaction to cancel.");
		}
		$authTxnId = $authTransaction->getTxnId(); //NOTE: NOT "getTransactionId()", which seems to return primary key
		
		$refTxn = new ReferenceTransactionDetails();
		$refTxn->setReferenceTransactionId($authTxnId);

		return [ self::REF_TXN_KEY => $refTxn ];
	}
}
