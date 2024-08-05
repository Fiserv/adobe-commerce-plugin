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
 * Refund Reference Transaction Data Builder
 */
class RefundRefTxnDataBuilder implements BuilderInterface
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

		$captureToRefund = $payment->getParentTransactionId() ?: $payment->getLastTransId();
		// $captureToRefund = $payment->getAuthorizationTransaction()->getAdditionalInformation('bluepay_capture_transaction_id');
		// if (!empty($captureToRefund)) {
		// 	$paymentToken = $captureToRefund;
		// }

		// $chargeTransaction = $payment->getAuthorizationTransaction();
		if (empty($captureToRefund)) 
		{
			throw new Exception("Unable to locate charge transaction to refund.");
		}
		//$chargeTxnId = $chargeTransaction->getTxnId(); //NOTE: NOT "getTransactionId()", which seems to return primary key
		
		$refTxn = new ReferenceTransactionDetails();
		$refTxn->setReferenceTransactionId($captureToRefund);

		return [ self::REF_TXN_KEY => $refTxn ];
	}
}
