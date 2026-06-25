<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Fiserv\Payments\Gateway\Response\CommerceHub\PaymentDetailsHandler;
use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;
use Magento\Sales\Model\Order\Payment;

/**
 * After Payment::authorize() runs, Magento core writes "Authorized amount of $X" using the
 * original pre-surcharge amount that was passed as a parameter to authorize(). By the time
 * that comment is written, our SurchargeResponseHandler has already updated amountAuthorized
 * to the correct surcharge-inclusive value. This plugin corrects the comment to reflect the
 * actual amount that Commerce Hub authorized on the card.
 */
class SurchargeAuthorizationComment
{
	/**
	 * After authorize() completes, correct the "Authorized amount of $X" status history comment
	 * to show the surcharge-inclusive total that Commerce Hub actually authorized.
	 *
	 * @param Payment $subject
	 * @param Payment $result
	 * @return Payment
	 */
	public function afterAuthorize(Payment $subject, Payment $result): Payment
	{
		// ...existing code...
	}

	/**
	 * After capture() completes, correct the "Captured amount of $X online" status history
	 * comment to show approvedAmount + capture surcharge (the actual amount CH captured).
	 *
	 * @param Payment $subject
	 * @param Payment $result
	 * @param mixed   $invoice
	 * @return Payment
	 */
	public function afterCapture(Payment $subject, Payment $result, $invoice = null): Payment
	{
		$captureSurcharge = $subject->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_CAPTURED_KEY);
		if (!$captureSurcharge || (float)$captureSurcharge <= 0) {
			return $result;
		}

		$order = $subject->getOrder();
		if (!$order) {
			return $result;
		}

		// approvedAmount.total (base amount without surcharge) is stored by PaymentDetailsHandler
		$txnInfo = $subject->getTransactionAdditionalInfo();
		$baseAmount = isset($txnInfo[PaymentDetailsHandler::KEY_AMOUNT])
			? (float)$txnInfo[PaymentDetailsHandler::KEY_AMOUNT]
			: 0.0;

		$totalCaptured = $baseAmount + (float)$captureSurcharge;
		$formattedAmount = $order->formatPrice($totalCaptured);

		foreach ($order->getStatusHistories() as $history) {
			if (!$history) {
				continue;
			}
			$comment = (string)$history->getComment();
			if (strpos($comment, 'Captured amount of') === false) {
				continue;
			}
			if (preg_match('/Captured amount of (.+?) online/u', $comment, $matches)) {
				$oldAmountStr = $matches[1];
				$corrected = str_replace(
					'Captured amount of ' . $oldAmountStr . ' online',
					'Captured amount of ' . $formattedAmount . ' online',
					$comment
				);
				$history->setComment($corrected);
			}
			break;
		}

		return $result;
	}
			return $result;
		}

		$order = $subject->getOrder();
		if (!$order) {
			return $result;
		}

		$correctedAmount = (float)$subject->getAmountAuthorized();
		$formattedAmount = $order->formatPrice($correctedAmount);

		foreach ($order->getStatusHistories() as $history) {
			if (!$history) {
				continue;
			}
			$comment = (string)$history->getComment();
			if (strpos($comment, 'Authorized amount of') === false) {
				continue;
			}

			// Extract the old formatted amount string from the comment so we can do a
			// precise literal replacement (avoids regex issues with currency symbols).
			if (preg_match('/Authorized amount of (.+?)\.(?:\s|$)/u', $comment, $matches)) {
				$oldAmountStr = $matches[1];
				$corrected = str_replace(
					'Authorized amount of ' . $oldAmountStr . '.',
					'Authorized amount of ' . $formattedAmount . '.',
					$comment
				);
				$history->setComment($corrected);
			}
			break;
		}

		return $result;
	}
}

