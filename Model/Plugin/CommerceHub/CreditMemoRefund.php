<?php

namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider;
use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\RefundAdapter;

class CreditMemoRefund
{
	protected $logger;

	public function __construct(MultiLevelLogger $logger) 
	{
		$this->logger = $logger;
    	}

	public function beforeRefund(
		RefundAdapter $subject,
		CreditmemoInterface $creditMemo,
		OrderInterface $order,
		$isOnline = false
	)
	{
		$payment = $order->getPayment();

		if ($payment && $payment->getMethod() === ConfigProvider::CODE) {
			$this->drawDownSurcharge($payment);
		}

		// if Grand Total is zero set refund method to offline
		// so we don't trigger the payment gateway capture command.
		if ($payment->getMethod() === ConfigProvider::CODE && $isOnline === true && $creditMemo->getGrandTotal() < 0.01)
		{
			$this->logger->logInfo(1, "Credit Memo Grand Total is 0.00. Setting refund mode to offline.");
			$isOnline = false;
		}

		return [$creditMemo, $order, $isOnline];
	}

	private function drawDownSurcharge(Payment $payment): void
	{
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

		$payment->setAdditionalInformation(
			SurchargeResponseHandler::SURCHARGE_REFUNDED_KEY,
			round($refunded + $allowed, 4)
		);
	}
}
