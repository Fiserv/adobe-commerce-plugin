<?php

namespace Fiserv\Payments\Model\Plugin\PayPal;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\PayPal\ConfigProvider;
use Magento\Sales\Model\Order\Payment;

class PayPalInvoiceRegister
{
	protected $logger;

	public function __construct(MultiLevelLogger $logger) 
	{
		$this->logger = $logger;
    	}

	public function beforeRegister(\Magento\Sales\Model\Order\Invoice $subject)
	{
		$order = $subject->getOrder();
		$payment = $order->getPayment();
		$remainingAuth = $payment->getBaseAmountAuthorized() - $payment->getBaseAmountPaid();	

		// if Grand Total is zero and there is remaining auth, then set Capture Case to offline
		// so we don't trigger the payment gateway capture command.
		if ($payment->getMethod() === ConfigProvider::CODE && $remainingAuth > 0 && $subject->getGrandTotal() < 0.01)
		{
			$this->logger->logInfo(1, "Invoice Grand Total is 0.00 and order has remaining authorization. Setting capture case to offline.");
			$subject->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
		}
	}
}
