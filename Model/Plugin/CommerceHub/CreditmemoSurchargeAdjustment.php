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

/**
 * Previously auto-filled the Adjustment Refund field with the surcharge amount.
 * That behaviour has been removed: the Adjustment Fee field is now left blank
 * and an "Estimated Surcharge Fee Refund" read-only line item is displayed above
 * Grand Total instead (rendered by SurchargeEstimate block / template).
 */
class CreditmemoSurchargeAdjustment
{
	public function afterCreateByOrder(
		CreditmemoFactory $subject,
		Creditmemo $result,
		Order $order,
		array $data = []
	) {
		return $result;
	}

	public function afterCreateByInvoice(
		CreditmemoFactory $subject,
		Creditmemo $result,
		Invoice $invoice,
		array $data = []
	) {
		return $result;
	}
}
