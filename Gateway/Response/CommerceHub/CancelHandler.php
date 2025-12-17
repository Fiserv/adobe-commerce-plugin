<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);
namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Class CancelHandler
 */
class CancelHandler extends PaymentDetailsHandler implements HandlerInterface
{
	public function handle(array $handlingSubject, array $response)
	{
		parent::handle($handlingSubject, $response);

 		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();
		$payment->setShouldCloseParentTransaction(true);
		$payment->setIsTransactionClosed(true);
	}
}
