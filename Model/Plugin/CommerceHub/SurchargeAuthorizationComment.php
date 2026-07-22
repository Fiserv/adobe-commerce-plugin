<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Fiserv\Payments\Gateway\Response\CommerceHub\PaymentDetailsHandler;
use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class SurchargeAuthorizationComment
{
	public function afterAuthorize(Payment $subject, Payment $result): Payment
	{
		if ((float)$subject->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY) <= 0) {
			return $result;
		}

		$this->replaceAmountInStatusComment(
			$subject->getOrder(),
			'/Authorized amount of (.+?)\.(?:\s|$)/u',
			'Authorized amount of %s.',
			$subject->getOrder()->formatPrice((float)$subject->getAmountAuthorized())
		);

		return $result;
	}

	public function afterCapture(Payment $subject, Payment $result, $invoice = null): Payment
	{
		if ((float)$subject->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY) <= 0) {
			return $result;
		}

		$transactionInfo = $subject->getTransactionAdditionalInfo();
		$captureAmount = (float)($transactionInfo[PaymentDetailsHandler::KEY_AMOUNT] ?? 0);
		if ($captureAmount <= 0) {
			return $result;
		}

		$order = $subject->getOrder();
		$this->replaceAmountInStatusComment(
			$order,
			'/Captured amount of (.+?) online/u',
			'Captured amount of %s online',
			$order->formatPrice($captureAmount)
		);

		return $result;
	}

	private function replaceAmountInStatusComment(
		Order $order,
		string $searchPattern,
		string $commentTemplate,
		string $correctedAmount
	): void {
		foreach ($order->getStatusHistories() as $history) {
			$comment = (string)$history->getComment();
			if (!preg_match($searchPattern, $comment, $matches)) {
				continue;
			}
			$history->setComment(str_replace(
				sprintf($commentTemplate, $matches[1]),
				sprintf($commentTemplate, $correctedAmount),
				$comment
			));
			break;
		}
	}
}
