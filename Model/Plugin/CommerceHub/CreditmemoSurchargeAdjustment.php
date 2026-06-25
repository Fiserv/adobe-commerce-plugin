<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;

class CreditmemoSurchargeAdjustment
{
	public function afterCreateByOrder(
		CreditmemoFactory $subject,
		Creditmemo $result,
		Order $order,
		array $data = []
	) {
		$this->applyEstimatedSurchargeToGrandTotal($result, $order);
		return $result;
	}

	public function afterCreateByInvoice(
		CreditmemoFactory $subject,
		Creditmemo $result,
		Invoice $invoice,
		array $data = []
	) {
		$this->applyEstimatedSurchargeToGrandTotal($result, $invoice->getOrder());
		return $result;
	}

	private function applyEstimatedSurchargeToGrandTotal(Creditmemo $creditmemo, Order $order): void
	{
		$payment = $order->getPayment();
		if (!$payment) {
			return;
		}

		$surcharge = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);
		if ($surcharge <= 0) {
			return;
		}

		$orderGrandTotal = (float)$order->getGrandTotal();
		$baseAmount = $orderGrandTotal - $surcharge; // non-surcharge portion of the order

		if ($baseAmount <= 0) {
			return;
		}

		$creditMemoTotal = (float)$creditmemo->getGrandTotal();
		$estimate = round($creditMemoTotal * $surcharge / $baseAmount, 4);

		if ($estimate <= 0) {
			return;
		}

		$creditmemo->setGrandTotal($creditMemoTotal + $estimate);
		$creditmemo->setBaseGrandTotal((float)$creditmemo->getBaseGrandTotal() + $estimate);
	}
}
