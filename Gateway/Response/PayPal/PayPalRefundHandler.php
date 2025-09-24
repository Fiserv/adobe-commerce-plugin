<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\PayPal;

use Fiserv\Payments\Gateway\Subject\PayPal\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class RefundHandler
 */
class PayPalRefundHandler extends PayPalPaymentDetailsHandler implements HandlerInterface
{
	//TODO: Confirm if this is the correct file exactly needed or not.

	private $subjectReader;
	private $logger;

	/**
	 * Constructor
	 *
	 * @param SubjectReader $subjectReader
	 * @param MultiLevelLogger $logger
	 */
	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
		parent::__construct($subjectReader, $logger);
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $handlingSubject, array $response)
	{
		parent::handle($handlingSubject, $response);

		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();

		$canRefundMore = $payment->getCreditmemo()->getInvoice()->canRefund();

		$payment->setShouldCloseParentTransaction(!$canRefundMore);
		$payment->setIsTransactionClosed(true);
	}
}
