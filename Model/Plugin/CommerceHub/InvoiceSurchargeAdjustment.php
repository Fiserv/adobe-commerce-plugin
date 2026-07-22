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

		$surchargeResponse = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);
		if ($surchargeResponse <= 0) {
			return;
		}

		$alreadyInvoicedSurcharge = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_INVOICED_KEY);

		$remainingSurchargeToInvoice = round($surchargeResponse - $alreadyInvoicedSurcharge, 4);
		if ($remainingSurchargeToInvoice <= 0) {
			return;
		}

		$subject->setGrandTotal((float)$subject->getGrandTotal() + $remainingSurchargeToInvoice);
		$subject->setBaseGrandTotal((float)$subject->getBaseGrandTotal() + $remainingSurchargeToInvoice);

		$payment->setAdditionalInformation(SurchargeResponseHandler::SURCHARGE_INVOICED_KEY, $surchargeResponse);
	}
}
