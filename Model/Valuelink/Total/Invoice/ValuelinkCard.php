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

		// if the invoice is for the entire order and a Valuelink SALE transaction
		// exists on the order, then the sale transaction should be applied to the invoice
		$saleAdjustment = 0.00;
		if ($this->invoiceHelper->isFullInvoice($order, $invoice))
		{
			// need to grab by order increment id because order may not have been created yet
			// for example: in the case of a sale, where an invoice is immediately created
			$valuelinkTxns = $this->orderHelper->getValuelinkTransactionsByOrderIncrementId($order->getIncrementId());
			$valuelinkTxns = $this->orderHelper->filterCanceledValuelinkTransactions($valuelinkTxns);
			
			foreach ($valuelinkTxns as $txn)
			{
				if (
					$txn[ValuelinkTransaction::KEY_TRANSACTION_TYPE] == ValuelinkTransaction::SALE_TYPE && 
					(is_null($txn[ValuelinkTransaction::KEY_INVOICE_ID]) || 
					$txn[ValuelinkTransaction::KEY_INVOICE_ID] == $invoice->getId())
				)
				{
					$saleAdjustment += floatval($txn[ValuelinkTransaction::KEY_AMOUNT]);
				}
			}
		} else {
			// if this is a partial invoice and Sales exist, need to account for all existing
			// invoices to determine whether existing sales have been accounted for.
			$saleAdjustment = $this->orderHelper->getUninvoicedSaleAmount($order);
		}
		
		if ($saleAdjustment > 0)
		{
			$total = $invoice->getGrandTotal();
			$rawTotal = $total - $saleAdjustment;
			$newTotal = $rawTotal > 0 ? $rawTotal : 0;
			$invoice->setBaseGrandTotal($newTotal);
			$invoice->setGrandTotal($newTotal);
		}

		$remainingAuth = $this->orderHelper->getRemainingAuthAmount($order);
		$valuelinkInvoiceAmount = $this->invoiceHelper->getValuelinkInvoiceAmount($order, $invoice, $remainingAuth);
		if ($valuelinkInvoiceAmount > 0)
		{
			$total = $invoice->getGrandTotal(); 
			$newTotal = $total - $valuelinkInvoiceAmount >= 0 ? $total - $valuelinkInvoiceAmount : 0;
			$invoice->setBaseGrandTotal($newTotal);
			$invoice->setGrandTotal($newTotal);
		}
		
		return $this;
	}
}
