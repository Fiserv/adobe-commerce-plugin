<?php

namespace Fiserv\Payments\Model\Plugin\Valuelink;

use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;

/**
 * Plugin to capture Valuelink authorizations when 
 * an invoice is created 
 */
class InvoiceRegister
{
	protected $orderHelper;
	
	public function __construct(
		ValuelinkOrderHelper $orderHelper
    ) {
		$this->orderHelper = $orderHelper;
    }

	public function beforeRegister(\Magento\Sales\Model\Order\Invoice $subject) 
	{
		$order = $subject->getOrder();
		$remainingAuth = $this->orderHelper->getRemainingAuthAmount($order);
		$uninvoicedSaleAmt = $this->orderHelper->getUninvoicedSaleAmount($order);

		// if Grand Total is zero and there is remaining auth, then set Capture Case to offline
		// so we don't trigger the payment gateway capture command.
		if (($remainingAuth > 0 || $uninvoicedSaleAmt > 0) && $subject->getGrandTotal() < 0.01)
		{	
			$subject->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
		}	
	}

}
