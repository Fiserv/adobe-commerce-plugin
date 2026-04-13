<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);
namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Magento\Payment\Gateway\Response\HandlerInterface;

/**
 * Class RefundHandler
 */
class RefundHandler extends PaymentDetailsHandler implements HandlerInterface
{
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
