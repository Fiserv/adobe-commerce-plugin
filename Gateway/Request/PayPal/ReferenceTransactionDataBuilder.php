<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal;

use \Fiserv\Payments\Gateway\Subject\PayPal\SubjectReader;
use \Fiserv\Payments\Lib\CommerceHub\Model\ReferenceTransactionDetails;
use \Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Observer\PayPal\DataAssignObserver;
use Exception;

/**
 * Payment Data Builder
 */
class ReferenceTransactionDataBuilder implements BuilderInterface
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
		$orderIncrementId = $orderDO->getOrderIncrementId();

		try{
		$authTransaction = $payment->getAuthorizationTransaction();
		if ($authTransaction && is_object($authTransaction))
		{
		$authTxnId = $authTransaction->getTxnId(); //NOTE: NOT "getTransactionId()", which seems to return primary key
		$this->logger->logDebug(3, "Auth Transaction ID found: $authTxnId" , "Order ID: $orderIncrementId");
		 if(!$authTxnId){
			$this->logger->logError(2, "Reference transaction data builder was unable to find auth transaction id" , "Order ID: $orderIncrementId");
		 }

		$refTxn = new ReferenceTransactionDetails();
		$refTxn->setReferenceTransactionId($authTxnId);

		$this->logger->logDebug(3, "Reference Transaction Data Builder:\n" . $refTxn->__toString(), "Order ID: $orderIncrementId");

		return [ self::REF_TXN_KEY => $refTxn ];
		}
		$paypalorderId = $payment->getAdditionalInformation(DataAssignObserver::ORDER_ID);
		if(!$paypalorderId){
			$this->logger->logError(2, "Reference transaction data builder was unable to find paypal order id" , "Order ID: $orderIncrementId");
			throw new Exception("Unable to locate paypal order id for reference transaction.");
		}
		$refTxn = new ReferenceTransactionDetails();
		$refTxn->setReferenceTransactionId($paypalorderId);
		$this->logger->logDebug(3, "Reference Transaction Data Builder:\n" . $refTxn->__toString(), "Order ID: $orderIncrementId");
		return [ self::REF_TXN_KEY => $refTxn ];
		}
		catch (\Exception $e)
		{
			$this->logger->logDebug(1, "Order ID: $orderIncrementId");
			return [];
		}
}
}
