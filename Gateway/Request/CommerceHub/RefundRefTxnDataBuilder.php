<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use \Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use \Fiserv\Payments\Lib\CommerceHub\Model\ReferenceTransactionDetails;
use \Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Refund Reference Transaction Data Builder
 */
class RefundRefTxnDataBuilder implements BuilderInterface
{
	const REF_TXN_KEY = "referenceTransaction";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @param MultiLevelLogger $logger
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
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
			$this->logger->logError(2, "Refund reference transaction data builder was unable to find auth transaction to refund");
			throw new Exception("Unable to locate charge transaction to refund.");
		}
		//$chargeTxnId = $chargeTransaction->getTxnId(); //NOTE: NOT "getTransactionId()", which seems to return primary key
		
		$refTxn = new ReferenceTransactionDetails();
		$refTxn->setReferenceTransactionId($captureToRefund);

		$this->logger->logInfo(3, "Refund Reference Transaction Data Builder:\n" . $refTxn->__toString());
		return [ self::REF_TXN_KEY => $refTxn ];
	}
}
