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
		$payment = $order->getPayment();
		if (!$payment) {
            return $result;
        }

		$invoicedSurcharge = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_INVOICED_KEY);
		$this->addProportionalSurchargeRefund($result, $order, $invoicedSurcharge);
		return $result;
	}

	public function afterCreateByInvoice(
		CreditmemoFactory $subject,
		Creditmemo $result,
		Invoice $invoice,
		array $data = []
	) {
		$order = $invoice->getOrder();
		if (!$order) {
			return $result;
		}

		$payment = $order->getPayment();
		if (!$payment) {
			return $result;
		}

		$invoicedSurcharge = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_INVOICED_KEY);
		$this->addProportionalSurchargeRefund($result, $order, $invoicedSurcharge);
		return $result;
	}

	private function addProportionalSurchargeRefund(Creditmemo $creditmemo, Order $order, float $invoicedSurcharge): void
	{
		if ($invoicedSurcharge <= 0) {
			return;
		}

		$orderGrandTotal = (float)$order->getGrandTotal();
		if ($orderGrandTotal <= 0) {
			return;
		}

		$orderMerchandiseTotal = $orderGrandTotal - $invoicedSurcharge;
		if ($orderMerchandiseTotal <= 0) {
			return;
		}

		$creditMemoMerchandiseTotal = (float)$creditmemo->getGrandTotal();
		$proportionalSurchargeRefund = round($creditMemoMerchandiseTotal * $invoicedSurcharge / $orderMerchandiseTotal, 4);

		if ($proportionalSurchargeRefund <= 0) {
			return;
		}

		$alreadyRefunded = (float)$order->getTotalRefunded();
		$remainingOrderBalanceAfterRefund = round($orderGrandTotal - $alreadyRefunded - $creditMemoMerchandiseTotal - $proportionalSurchargeRefund, 4);

		if ($remainingOrderBalanceAfterRefund > 0 && $remainingOrderBalanceAfterRefund <= 0.50) {
			$proportionalSurchargeRefund = round($proportionalSurchargeRefund + $remainingOrderBalanceAfterRefund, 4);
		}

		$creditmemo->setGrandTotal($creditMemoMerchandiseTotal + $proportionalSurchargeRefund);
		$creditmemo->setBaseGrandTotal((float)$creditmemo->getBaseGrandTotal() + $proportionalSurchargeRefund);
	}
}
