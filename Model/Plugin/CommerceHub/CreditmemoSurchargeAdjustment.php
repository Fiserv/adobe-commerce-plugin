<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;

/** Reads the surcharge value returned by CommerceHub & updates the status History Comment */

class CreditmemoSurchargeAdjustment
{
	public function afterCreateByOrder(
		CreditmemoFactory $subject,
		Creditmemo $result,
		Order $order,
		array $data = []
	) {
		if ($this->adminProvidedAdjustment($data)) {
			return $result;
		}
		$this->applySurcharge($result, $order);
		return $result;
	}

	public function afterCreateByInvoice(
		CreditmemoFactory $subject,
		Creditmemo $result,
		Invoice $invoice,
		array $data = []
	) {
		if ($this->adminProvidedAdjustment($data)) {
			return $result;
		}
		$this->applySurcharge($result, $invoice->getOrder());
		return $result;
	}

	private function adminProvidedAdjustment(array $data): bool
	{
		return $this->hasNonZeroValue($data, 'adjustment_positive')
			|| $this->hasNonZeroValue($data, 'adjustment_negative');
	}

	private function hasNonZeroValue(array $data, string $key): bool
	{
		if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
			return false;
		}
		return abs((float)$data[$key]) > 0.0;
	}

	private function applySurcharge(Creditmemo $creditmemo, Order $order): void
	{
		$payment = $order->getPayment();
		if (!$payment) {
			return;
		}

		$surcharge = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);
		if ($surcharge <= 0) {
			return;
		}

		$invoiced = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_INVOICED_KEY);
		$capturedSurcharge = $invoiced > 0 ? $invoiced : $surcharge;

		$refunded = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_REFUNDED_KEY);

		$allowed = round($capturedSurcharge - $refunded, 4);
		if ($allowed <= 0) {
			return;
		}

		$creditmemo->setAdjustmentPositive((float)$creditmemo->getAdjustmentPositive() + $allowed);
		$creditmemo->setBaseAdjustmentPositive((float)$creditmemo->getBaseAdjustmentPositive() + $allowed);
		$creditmemo->setGrandTotal((float)$creditmemo->getGrandTotal() + $allowed);
		$creditmemo->setBaseGrandTotal((float)$creditmemo->getBaseGrandTotal() + $allowed);
		$creditmemo->setAdjustment((float)$creditmemo->getAdjustment() + $allowed);
		$creditmemo->setBaseAdjustment((float)$creditmemo->getBaseAdjustment() + $allowed);
	}
}
