<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Magento\Sales\Model\Order\Invoice;
use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;

/** Ensures the invoice's grand totals include the CommerceHub surcharge */
class InvoiceSurchargeAdjustment
{
	public function beforePay(Invoice $subject): void
	{
		$order = $subject->getOrder();
		if (!$order) {
			return;
		}

		$payment = $order->getPayment();
		if (!$payment) {
			return;
		}

		$surcharge = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);
		if ($surcharge <= 0) {
			return;
		}

		$remainingOrderTotal = (float)$order->getGrandTotal() - (float)$order->getTotalInvoiced();
		$remainingBaseOrderTotal = (float)$order->getBaseGrandTotal() - (float)$order->getBaseTotalInvoiced();

		$gap = round($remainingOrderTotal - (float)$subject->getGrandTotal(), 4);
		$baseGap = round($remainingBaseOrderTotal - (float)$subject->getBaseGrandTotal(), 4);

		$bump = min(max($gap, 0), $surcharge);
		$baseBump = min(max($baseGap, 0), $surcharge);

		if ($bump > 0) {$subject->setGrandTotal((float)$subject->getGrandTotal() + $bump);}

		if ($baseBump > 0) {$subject->setBaseGrandTotal((float)$subject->getBaseGrandTotal() + $baseBump);}
	}
}
