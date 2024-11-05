<?php

namespace Fiserv\Payments\Block\Valuelink\Adminhtml\Sales\Invoice;

use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;
use Fiserv\Payments\Model\Valuelink\Helper\Invoice\ValuelinkInvoiceHelper;
use Fiserv\Payments\Model\ValuelinkTransaction;

class Totals extends \Fiserv\Payments\Block\Valuelink\Adminhtml\Sales\Order\Totals 
{	
	private $orderHelper;
	
	private $invoiceHelper;

	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		ValuelinkOrderHelper $orderHelper,
		ValuelinkInvoiceHelper $invoiceHelper,
		\Magento\Framework\Registry $registry,
		\Magento\Sales\Helper\Admin $adminHelper,
		array $data = []
	) {
		$this->orderHelper = $orderHelper;
		$this->invoiceHelper = $invoiceHelper;
		parent::__construct($context, $orderHelper, $registry, $adminHelper, $data);
		$this->_isScopePrivate = true;
	}

	public function getInvoice()
	{
		return $this->getParentBlock()->getInvoice();
	}

	public function getInvoiceTotal()
	{
		return $this->getInvoice()->getGrandTotal();
	}

	public function getValuelinkTotalForNewInvoice()
	{
		$order = $this->getOrder();
		$invoice = $this->getInvoice();
		$remainingAuth = $this->orderHelper->getRemainingAuthAmount($order);

		// Need to handle cases where we need to capture a VL auth OR apply a VL sale txn to this invoice. 
		// It will never be the case that we both need to capture a VL auth AND apply value from a VL sale to an invoice.
		// First calculate amount needed to capture
		$amtToCapture = $this->invoiceHelper->getValuelinkInvoiceAmount($order, $invoice, $remainingAuth);
		// If no captures are required, calculate how much sale has beena pplied to the invoice
		return $amtToCapture > 0 ? $amtToCapture : $this->invoiceHelper->getValuelinkBalanceAppliedToInvoice($invoice);
	
	}

	public function getValuelinkTotalCapturedOnInvoice()
	{
		return $this->invoiceHelper->getValuelinkBalanceAppliedToInvoice($this->getInvoice());
	}

	
}
