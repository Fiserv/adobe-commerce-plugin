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
		$orderIncrementId = $orderDO->getOrderIncrementId();

		// LastTransId changes after every capture/refund. The selected invoice
		// retains its capture ID, including for repeated partial refunds and
		// refunds of an older invoice on a multiple-capture order.
		$captureToRefund = '';
		if ($payment->getMethod() === 'fiserv_pay_by_link') {
			$creditmemo = $payment->getCreditmemo();
			$invoice = $creditmemo ? $creditmemo->getInvoice() : null;
			$captureToRefund = $invoice ? (string) $invoice->getTransactionId() : '';
		} else {
			$captureToRefund = $payment->getParentTransactionId() ?: $payment->getLastTransId();
		}

		if (empty($captureToRefund)) 
		{
			$this->logger->logError(2, "Refund reference transaction data builder was unable to find capture transaction to refund", "Order ID: $orderIncrementId");
			throw new LocalizedException(__("Unable to locate charge transaction to refund."));
		}
		//$chargeTxnId = $chargeTransaction->getTxnId(); //NOTE: NOT "getTransactionId()", which seems to return primary key
		
		$refTxn = new ReferenceTransactionDetails();
		$refTxn->setReferenceTransactionId($captureToRefund);

		$this->logger->logDebug(3, "Refund Reference Transaction Data Builder:\n" . $refTxn->__toString(), "Order ID: $orderIncrementId");

		return [ self::REF_TXN_KEY => $refTxn ];
	}
}
