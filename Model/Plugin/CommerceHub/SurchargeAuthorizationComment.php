<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Fiserv\Payments\Gateway\Response\CommerceHub\PaymentDetailsHandler;
use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;
use Magento\Sales\Model\Order\Payment;

class SurchargeAuthorizationComment
{

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
		if (!$captureSurcharge || (float)$captureSurcharge <= 0) {
			return $result;
		}

		$order = $subject->getOrder();
		if (!$order) {
			return $result;
		}

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
