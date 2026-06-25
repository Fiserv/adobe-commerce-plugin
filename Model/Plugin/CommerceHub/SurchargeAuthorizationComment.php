<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Plugin\CommerceHub;

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

