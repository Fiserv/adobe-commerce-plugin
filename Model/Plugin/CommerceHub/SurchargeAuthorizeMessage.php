<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;

/** Rewrites the most-recent status-history so it reflects the updated grand total */
class SurchargeAuthorizeMessage
{
	public function afterAuthorize(Payment $subject, $result, $isOnline, $amount)
	{
		$surcharge = (float)$subject->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);
		if ($surcharge <= 0) {
			return $result;
		}

		$this->rewriteAmountInLatestHistory($subject, (float)$amount, $surcharge);
		return $result;
	}

	public function afterCapture(Payment $subject, $result, Invoice $invoice = null)
	{
		$surcharge = (float)$subject->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);
		if ($surcharge <= 0) {
			return $result;
		}

		$amountAuthorized = (float)$subject->getAmountAuthorized();
		if ($amountAuthorized <= 0) {return $result;}

		$originalAmount = $amountAuthorized - $surcharge;
		$this->rewriteAmountInLatestHistory($subject, $originalAmount, $surcharge);
		return $result;
	}

	private function rewriteAmountInLatestHistory(Payment $payment, float $originalAmount, float $surcharge): void
	{
		$order = $payment->getOrder();
		if (!$order) {
			return;
		}

		$oldFormatted = $order->getBaseCurrency()->formatTxt($originalAmount);
		$newFormatted = $order->getBaseCurrency()->formatTxt($originalAmount + $surcharge);

		$histories = $order->getStatusHistories();
		if (!is_array($histories)) {
			return;
		}

		foreach (array_reverse($histories) as $history) {
			$comment = (string)$history->getComment();
			if ($comment === '') {
				continue;
			}
			if (strpos($comment, $newFormatted) !== false) {
				return;
			}
			if (strpos($comment, $oldFormatted) === false) {
				continue;
			}
			$history->setComment(str_replace($oldFormatted, $newFormatted, $comment));
			return;
		}
	}
}
