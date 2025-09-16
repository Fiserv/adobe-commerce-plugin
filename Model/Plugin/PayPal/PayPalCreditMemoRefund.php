<?php

namespace Fiserv\Payments\Model\Plugin\PayPal;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\PayPal\ConfigProvider;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\RefundAdapter;

class PayPalCreditMemoRefund
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

		// if Grand Total is zero set refund method to offline
		// so we don't trigger the payment gateway capture command.
		if ($payment->getMethod() === ConfigProvider::CODE && $isOnline === true && $creditMemo->getGrandTotal() < 0.01)
		{
			$this->logger->logInfo(1, "Credit Memo Grand Total is 0.00. Setting refund mode to offline.");
			$isOnline = false;
		}

		return [$creditMemo, $order, $isOnline];
	}
}
