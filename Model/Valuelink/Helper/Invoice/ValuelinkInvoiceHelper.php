<?php

namespace Fiserv\Payments\Model\Valuelink\Helper\Invoice;

use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Fiserv\Payments\Model\ValuelinkTransaction;

class ValuelinkInvoiceHelper
{
	private $valuelinkResource;

	public function __construct(
		ValuelinkResource $valuelinkResource
	) {
		$this->valuelinkResource = $valuelinkResource;
	}

	public function isFullInvoice($order, $invoice)
	{
		// if different number of items, it is a partial invoice
		if ($this->getTotalInvoiceItems($invoice) < $this->getTotalOrderItems($order))
		{
			return false;
		}

		// invoice total can be greater than order total if we have an unreconciled
		// valuelink sale processed before the invoice;
		return round($order->getGrandTotal(), 2) <= round($invoice->getGrandTotal(), 2);
	}

	private function getTotalInvoiceItems($invoice)
	{
		$total = 0;
		$items = $invoice->getAllItems();
		foreach($items as $item)
		{
			$total += $item->getQty();
		}
		return $total;
	}

	private function getTotalOrderItems($order)
	{
		$total = 0;
		$items = $order->getAllItems();
		foreach($items as $item)
		{
			$total += $item->getQtyOrdered();
		}
		return $total;
	}

	public function getValuelinkInvoiceAmount($order, $invoice, $remainingAuth)
	{
		// Invoicing the whole order should capture all Valuelink auths
		if ($this->isFullInvoice($order, $invoice))
		{
			return $remainingAuth;
		}

		$invoiceTotal = $invoice->getGrandTotal();
		return $invoiceTotal > 0 ? $remainingAuth : $invoice->getSubtotal() + $invoice->getShippingAmount();
	}

	// returns an ordered array of authorizations to capture
	// to cover the balance of the invoice. Possible that
	// existing auths aren't sufficient to cover balance, 
	// in which case another payment method should already
	// be registered to the order and should cover the remainder
	public function getAuthCaptureSequence($total, $balancedAuths)
	{
		// Order of preference for captures:
		// 1. Total can be exactly covered by one gift card
		// 2. Total can be completely covered by one gift card with remainder
		// 3. Consume gift cards in order of greatest available balance to least;

		// sort auths by remaining balance
		usort($balancedAuths, function($a, $b) { 
			if ($a[ValuelinkTransaction::KEY_AMOUNT] == $b[ValuelinkTransaction::KEY_AMOUNT])
			{
				return 0;
			}
			
			return $a[ValuelinkTransaction::KEY_AMOUNT] < $b[ValuelinkTransaction::KEY_AMOUNT] ? -1 : 1; 
		});	
	
		$authOrder = array();

		// Look for exact match	
		foreach ($balancedAuths as $auth)
		{ 
			if ($auth[ValuelinkTransaction::KEY_AMOUNT] == $total)
			{
				$details = array();
				$details["auth"] = $auth["auth"];
				$details["captureAmount"] = $total;
				$details["captures"] = $auth["captures"];
				$details["final"] = true;
				array_push($authOrder, $details);
				return $authOrder;
			}
		}

		// Cover largest balances first
		foreach ($balancedAuths as $auth)
		{
			$details = array();
			$details["auth"] = $auth["auth"];
			$details["captureAmount"] = $total <= $auth[ValuelinkTransaction::KEY_AMOUNT] ? $total : $auth[ValuelinkTransaction::KEY_AMOUNT];
			$details["captures"] = $auth["captures"];
			$details["final"] = round($details["captureAmount"],2) == round($auth[ValuelinkTransaction::KEY_AMOUNT],2);
			array_push($authOrder, $details);

			$total -= $details["captureAmount"];

			if ($total < 0.01)
			{
				break;
			}
		}

		return $authOrder;
	}

	public function getValuelinkTransactionsByInvoice(?\Magento\Sales\Model\Order\Invoice $invoice)
	{
		if ($invoice == null)
		{
			return array();
		}

		return $this->valuelinkResource->getByInvoiceId($invoice->getId());
	}

	public function getPrimaryValuelinkTransactionsByInvoice(?\Magento\Sales\Model\Order\Invoice $invoice)
	{
		if ($invoice == null)
		{
			return array();
		}

		$rawTxns = $this->getValuelinkTransactionsByInvoice($invoice);
		$primaryTxns = array();

		$primaryTransactionTypes = [ValuelinkTransaction::AUTHORIZE_TYPE, ValuelinkTransaction::SALE_TYPE];
		foreach($rawTxns as $txn)
		{
			if (in_array($txn[ValuelinkTransaction::KEY_TRANSACTION_TYPE], $primaryTransactionTypes))
			{
				array_push($primaryTxns, $txn);
			}
		}

		return $primaryTxns;
	}

	public function getCapturedValuelinkTransactionsByInvoice(?\Magento\Sales\Model\Order\Invoice $invoice)
	{
		if ($invoice == null)
		{
			return array();
		}

		$rawTxns = $this->getValuelinkTransactionsByInvoice($invoice);
		
		$captureTxns = array();
		foreach($rawTxns as $txn)
		{
			if($txn[ValuelinkTransaction::KEY_TRANSACTION_STATE] === ValuelinkTransaction::CAPTURED_STATE)
			{
				array_push($captureTxns, $txn);
			}
		}

		return $captureTxns;
	}

	public function getCapturedValuelinkAmountByInvoice(?\Magento\Sales\Model\Order\Invoice $invoice)
	{
		$amount = 0;
		if ($invoice == null)
		{
			return $amount;
		}
	
		$captures = $this->getCapturedValuelinkTransactionsByInvoice($invoice);

		foreach ($captures as $capture)
		{
			$amount += $capture[ValuelinkTransaction::KEY_AMOUNT];
		}

		return $amount;
	}

	// this isn't an ideal calculation as there may be custom
	// modifications to the total that aren't accounted for here
	// VALIDATE THAT SALE TRANSACTIONS EXIST ON THE ORDER FIRST
	public function getValuelinkSaleBalanceAppliedToInvoice(\Magento\Sales\Model\Order\Invoice $invoice)
	{
		$subTotal = $invoice->getSubtotal() ?? 0;
		$tax = $invoice->getTaxAmount() ?? 0;
		$shipping = $invoice->getShippingAmount() ?? 0;
		$shippingTax = $invoice->getShippingTaxAmount() ?? 0;
		$rawTotal = $subTotal + $tax + $shipping + $shippingTax;

		$discount = $invoice->getDiscountAmount() ?? 0;
		$storeCredit = $invoice->getCustomerBalanceAmount() ?? 0;
		$mageGift = $invoice->getGiftCardsAmount() ?? 0;
		$deductions = $discount + $storeCredit;

		return $rawTotal - $deductions - $invoice->getGrandTotal();
	}

	// could have applied gift card funds from a vl sale txn associated with order
	// preference captures directly associated with invoice
	public function getValuelinkBalanceAppliedToInvoice(?\Magento\Sales\Model\Order\Invoice $invoice)
	{
		if (is_null($invoice))
		{
			return 0;
		}
		$invoiceAmt = $this->getCapturedValuelinkAmountByInvoice($invoice);
		return $invoiceAmt > 0 ? $invoiceAmt : $this->getValuelinkSaleBalanceAppliedToInvoice($invoice);
	}
}
