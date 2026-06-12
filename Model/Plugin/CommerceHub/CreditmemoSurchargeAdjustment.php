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
		return array_key_exists('adjustment_positive', $data) || array_key_exists('adjustment_negative', $data);
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

		$alreadyRefunded = 0.0;
		foreach ($order->getCreditmemosCollection() as $existing) {
			$alreadyRefunded += (float)$existing->getAdjustmentPositive();
		}

		$remaining = round($surcharge - $alreadyRefunded, 4);
		if ($remaining <= 0) {
			return;
		}

		$creditmemo->setAdjustmentPositive((float)$creditmemo->getAdjustmentPositive() + $remaining);
		$creditmemo->setBaseAdjustmentPositive((float)$creditmemo->getBaseAdjustmentPositive() + $remaining);
		$creditmemo->setGrandTotal((float)$creditmemo->getGrandTotal() + $remaining);
		$creditmemo->setBaseGrandTotal((float)$creditmemo->getBaseGrandTotal() + $remaining);
		$creditmemo->setAdjustment((float)$creditmemo->getAdjustment() + $remaining);
		$creditmemo->setBaseAdjustment((float)$creditmemo->getBaseAdjustment() + $remaining);
	}
}
