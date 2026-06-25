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
 * Corrects order status history comments written by Magento core after auth/capture.
 *
 * Magento's Payment::authorize() and Payment::capture() write "Authorized/Captured amount of $X"
 * using the original pre-surcharge amount passed as a method parameter. By the time those comments
 * are written, SurchargeResponseHandler has already updated amountAuthorized and stored the
 * surcharge in additional_information. These after-plugins replace the stale amount in the comment
 * with the actual surcharge-inclusive total that Commerce Hub authorized/captured.
 */
class SurchargeAuthorizationComment
{
	/**
	 * Correct "Authorized amount of $X" to show the surcharge-inclusive authorized total.
	 */
	public function afterAuthorize(Payment $subject, Payment $result): Payment
	{
		$surcharge = $subject->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);
		if (!$surcharge || (float)$surcharge <= 0) {
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
			if (preg_match('/Authorized amount of (.+?)\.(?:\s|$)/u', $comment, $matches)) {
				$corrected = str_replace(
					'Authorized amount of ' . $matches[1] . '.',
					'Authorized amount of ' . $formattedAmount . '.',
					$comment
				);
				$history->setComment($corrected);
			}
			break;
		}

		return $result;
	}

	public function afterCapture(Payment $subject, Payment $result, $invoice = null): Payment
	{
		$captureSurcharge = $subject->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_CAPTURED_KEY);
		$authSurcharge    = $subject->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);

		$txnInfo = $subject->getTransactionAdditionalInfo();
		$approvedAmount = isset($txnInfo[PaymentDetailsHandler::KEY_AMOUNT])
			? (float)$txnInfo[PaymentDetailsHandler::KEY_AMOUNT]
			: 0.0;

		if ($captureSurcharge && (float)$captureSurcharge > 0) {
			$totalCaptured = $approvedAmount + (float)$captureSurcharge;
		} elseif ($authSurcharge && (float)$authSurcharge > 0) {
			$totalCaptured = $approvedAmount;
		} else {
			return $result;
		}

		if ($totalCaptured <= 0) {
			return $result;
		}

		$order = $subject->getOrder();
		if (!$order) {
			return $result;
		}

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
				$corrected = str_replace(
					'Captured amount of ' . $matches[1] . ' online',
					'Captured amount of ' . $formattedAmount . ' online',
					$comment
				);
				$history->setComment($corrected);
			}
			break;
		}

		return $result;
	}
}
