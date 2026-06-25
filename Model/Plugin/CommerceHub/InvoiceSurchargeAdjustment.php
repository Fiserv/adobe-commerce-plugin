<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Magento\Sales\Model\Order\Invoice;
use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;

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

		$invoiced = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_INVOICED_KEY);

		$allowed = round($surcharge - $invoiced, 4);
		if ($allowed <= 0) {
			return;
		}

		$subject->setGrandTotal((float)$subject->getGrandTotal() + $allowed);
		$subject->setBaseGrandTotal((float)$subject->getBaseGrandTotal() + $allowed);

		$payment->setAdditionalInformation(
			SurchargeResponseHandler::SURCHARGE_INVOICED_KEY,
			round($invoiced + $allowed, 4)
		);
	}
}
