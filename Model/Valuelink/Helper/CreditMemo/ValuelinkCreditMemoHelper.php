<?php

namespace Fiserv\Payments\Model\Valuelink\Helper\CreditMemo;

use Fiserv\Payments\Model\Valuelink\Helper\Invoice\ValuelinkInvoiceHelper;
use Fiserv\Payments\Model\ValuelinkTransaction;
use Psr\Log\LoggerInterface;

class ValuelinkCreditMemoHelper
{
	private $invoiceHelper;

	private $logger;

	public function __construct(
		ValuelinkInvoiceHelper $invoiceHelper,
		LoggerInterface $logger
	) {
		$this->invoiceHelper = $invoiceHelper;
		$this->logger = $logger;
	}

	public function isFullCreditMemo($creditMemo, $invoice)
	{
		// if different number of items, it is a partial credit memo
		if ($this->getTotalItems($creditMemo) < $this->getTotalItems($invoice))
		{
			return false;
		}

		$vlCapturedAmt = $this->invoiceHelper->getCapturedValuelinkAmountByInvoice($invoice);	
		return round($creditMemo->getGrandTotal(), 2) == round($invoice->getGrandTotal() + $vlCapturedAmt, 2);
	}

	private function getTotalItems($obj)
	{
		$total = 0;
		$items = $obj->getAllItems();
		foreach($items as $item)
		{
			$total += $item->getQty();
		}
		return $total;
	}

	// refunds should bias for captured payments first,
	// then valuelink captures because of the undetermined 
	// method of refunding valuelink captures
	public function getCreditMemoAdjustment($creditMemo)
	{
		$invoice = $creditMemo->getInvoice();
		$vlCapturedAmt = $this->invoiceHelper->getValuelinkBalanceAppliedToInvoice($invoice);

		// if no Valuelink captures on the invoice, no modification necessary 
		if ($vlCapturedAmt < 0.01)
		{
			$creditMemo->setValuelinkAdjustment(0);
			return $creditMemo->getValuelinkAdjustment();
		}
		
		// If the credit memo is full, the amount is the credit memo minus any valuelink captures
		if ($this->isFullCreditMemo($creditMemo, $invoice))
		{
			$creditMemo->setValuelinkAdjustment($vlCapturedAmt);
			return $creditMemo->getValuelinkAdjustment();
		}

		// if partial refund, has the invoice grandTotal been satisfied
		// by previous refunds? If not, the remaining invoice amount
		// not already refunded should be covered by credit memo.
		$totalExistingRefunds = 0;
		foreach($this->getInvoiceCreditMemos($invoice) as $cm)
		{
			$totalExistingRefunds += $cm->getGrandTotal();
		}

		// there is more non-VL payment to refund
		if ($invoice->getGrandTotal() > $totalExistingRefunds)
		{
			$diff = $invoice->getGrandTotal() - $totalExistingRefunds;
			$rawAdj = -($diff - $creditMemo->getGrandTotal());
			// Adjustment could be greater than captured Valuelink amount 
			// if store credit has been refunded offline	
			$rawAdj = $rawAdj <= $vlCapturedAmt ? $rawAdj : $vlCapturedAmt;
			$creditMemo->setValuelinkAdjustment($rawAdj > 0 ? $rawAdj : 0);
			return $creditMemo->getValuelinkAdjustment();
		}

		$creditMemo->setValuelinkAdjustment($creditMemo->getGrandTotal());
		return $creditMemo->getValuelinkAdjustment();
	}

	public function getInvoiceCreditMemos($invoice)
	{
		$order = $invoice->getOrder();
		
		$orderCms = $order->getCreditmemosCollection();

		$invoiceCms = array();

		foreach($orderCms as $cm)
		{
			if ($cm->getInvoiceId() == $invoice->getId())
			{
				array_push($invoiceCms, $cm);
			}
		}

		return $invoiceCms;
	}

	public function getValuelinkAmountAppliedToCreditMemo($creditMemo)
	{
		return -($creditMemo->getGrandTotal() - 
			$creditMemo->getSubtotal() -
			$creditMemo->getShippingAmount() -
			$creditMemo->getShippingTaxAmount() -
			$creditMemo->getTaxAmount() +
			$creditMemo->getDiscountAmount() + 
			$creditMemo->getCustomerBalanceAmount() + 
			$creditMemo->getGiftCardsAmount());
	}

}
