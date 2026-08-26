<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use \Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use \Fiserv\Payments\Lib\CommerceHub\Model\ReferenceTransactionDetails;
use \Magento\Payment\Gateway\Request\BuilderInterface;
use \Magento\Framework\Exception\LocalizedException;
use Fiserv\Payments\Logger\MultiLevelLogger;


/**
 * Cancel Reference Transaction Data Builder
 */
class CancelRefTxnDataBuilder implements BuilderInterface
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

		$referenceTxnId = '';
		if ($payment->getMethod() === 'fiserv_pay_by_link') {
			$referenceTxnId = (string) $payment->getAdditionalInformation('pbl_transaction_id');
		}

		if ($referenceTxnId === '') {
			$authTransaction = $payment->getAuthorizationTransaction();
			if ($authTransaction == null)
			{
				throw new LocalizedException(__("Unable to locate auth transaction to cancel."));
			}
			$referenceTxnId = (string) $authTransaction->getTxnId(); // NOTE: NOT getTransactionId()
		}
		
		$refTxn = new ReferenceTransactionDetails();
		$refTxn->setReferenceTransactionId($referenceTxnId);

		$orderIncrementId = $orderDO->getOrderIncrementId();
		$this->logger->logDebug(3, "Cancel Reference Transaction Data Builder:\n" . $refTxn->__toString(),"Order ID: $orderIncrementId");

		return [ self::REF_TXN_KEY => $refTxn ];
	}
}
