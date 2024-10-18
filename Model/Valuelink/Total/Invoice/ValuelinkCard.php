<?php

namespace Fiserv\Payments\Model\Valuelink\Total\Invoice;

use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;
use Fiserv\Payments\Model\Valuelink\Helper\Invoice\ValuelinkInvoiceHelper;
use Fiserv\Payments\Model\ValuelinkTransaction;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class ValuelinkCard extends \Magento\Sales\Model\Order\Invoice\Total\AbstractTotal
{
	private $orderHelper;

	private $invoiceHelper;

	private $priceCurrency;

	public function __construct(
		ValuelinkOrderHelper $orderHelper,
		ValuelinkInvoiceHelper $invoiceHelper,
		PriceCurrencyInterface $priceCurrency
	){
		$this->orderHelper = $orderHelper;
		$this->invoiceHelper = $invoiceHelper;
		$this->priceCurrency = $priceCurrency;
	}
		
	public function collect(\Magento\Sales\Model\Order\Invoice $invoice) 
	{
		$order = $invoice->getOrder();
		
		$remainingAuth = $this->orderHelper->getRemainingAuthAmount($order);
		$valuelinkInvoiceAmount = $this->invoiceHelper->getValuelinkInvoiceAmount($order, $invoice, $remainingAuth);
		
		if ($valuelinkInvoiceAmount > 0.01)
		{
			$total = $invoice->getGrandTotal(); 
			$newTotal = $total - $valuelinkInvoiceAmount >= 0 ? $total - $valuelinkInvoiceAmount : 0;
			$invoice->setBaseGrandTotal($newTotal);
			$invoice->setGrandTotal($newTotal);
		}
		
		return $this;
	}
}
