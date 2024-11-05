<?php

namespace Fiserv\Payments\Model\Valuelink\Helper\Order;

use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Fiserv\Payments\Model\Valuelink\Helper\Invoice\ValuelinkInvoiceHelper;
use Fiserv\Payments\Model\ValuelinkTransaction;

class ValuelinkOrderHelper
{
	private $valuelinkResource;

	private $invoiceHelper;

	public function __construct(
		ValuelinkResource $valuelinkResource,
		ValuelinkInvoiceHelper $invoiceHelper
	) {
		$this->valuelinkResource = $valuelinkResource;
		$this->invoiceHelper = $invoiceHelper;
	}

	public function getValuelinkTransactions($order)
	{
		return $this->valuelinkResource->getByOrderId($order->getId());
	}

	public function getValuelinkTransactionsByOrderIncrementId($orderIncrementId)
	{
		return $this->valuelinkResource->getByOrderIncrementId($orderIncrementId);
	}

	public function getPrimaryValuelinkTransactionsByOrder($order)
	{
		$rawTxns = $this->getValuelinkTransactions($order);
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
		
	public function getRemainingAuthAmount($order, $txns = null)
	{
		$txns = $txns ?? $this->filterCanceledValuelinkTransactions($this->getValuelinkTransactionsByOrderIncrementId($order->getIncrementId()));

		$authTotal = 0;
		$captureTotal = 0;
		foreach($txns as $txn)
		{
			if ($txn[ValuelinkTransaction::KEY_TRANSACTION_TYPE] === ValuelinkTransaction::AUTHORIZE_TYPE)
			{
				$authTotal += $txn[ValuelinkTransaction::KEY_AMOUNT];
			}
			elseif ($txn[ValuelinkTransaction::KEY_TRANSACTION_TYPE] === ValuelinkTransaction::CAPTURE_TYPE)
			{
				$captureTotal += $txn[ValuelinkTransaction::KEY_AMOUNT];
			}
		}

		return $authTotal - $captureTotal;
	}

	// Returns an assoc array with original auth and remaining balance available to capture
	public function getBalancedAuths($order, $txns = null)
	{
		$txns = $txns ?? $this->getValuelinkTransactions($order);
		$auths = array();
		$captures = array();

		foreach($txns as $txn)
		{
			if ($txn[ValuelinkTransaction::KEY_TRANSACTION_STATE] === ValuelinkTransaction::AUTHORIZED_STATE)
			{
				array_push($auths, $txn);
			}

			elseif ($txn[ValuelinkTransaction::KEY_TRANSACTION_STATE] === ValuelinkTransaction::CAPTURED_STATE)
			{
				array_push($captures, $txn);
			}
		}

		$balancedAuths = array();
		foreach($auths as $auth)
		{
			$amt = $auth[ValuelinkTransaction::KEY_AMOUNT];
			$previousCaptures = 0;

			foreach($captures as $capture)
			{
				if ($capture[ValuelinkTransaction::KEY_PARENT_TRANSACTION_ID] === $auth[ValuelinkTransaction::KEY_ENTITY_ID])
				{
					$amt -= $capture[ValuelinkTransaction::KEY_AMOUNT];
					$previousCaptures++;
				}
			}

			if ($amt >= 0.01)
			{
				$details = array();
				$details["auth"] = $auth;
				$details["amount"] = $amt;
				$details["captures"] = $previousCaptures;
				array_push($balancedAuths, $details);
			}
		}

		return $balancedAuths;
	}

	// Finds the sum of all Valuelinkg Sale transactions attached to an order
	public function sumOfValuelinkSales($order)
	{
		$txns = $this->getValuelinkTransactions($order);
		$txns = $this->filterCanceledValuelinkTransactions($txns);

		$saleSum = 0;
		foreach ($txns as $txn)
		{
			if ($txn[ValuelinkTransaction::KEY_TRANSACTION_TYPE] == ValuelinkTransaction::SALE_TYPE)
			{
				$saleSum += $txn[ValuelinkTransaction::KEY_AMOUNT];
			}
		}

		return $saleSum;
	}

	// There's seemingly a bug in Magento 2.4.7+ where orders created via reorder
	// have an increment id with a "-#" suffix that isn't autoincremented. This means if
	// a order created through reorder fails the suffix may be reused, which can cause issues
	// if we've already failed a transaction with a Valuelink card attached.
	// We need to reconcile canceled auth/capture/sale transactions as a result, 
	// since they may exist because of failed reorder attempts.
	public function filterCanceledValuelinkTransactions(array $transactions): array
	{
		$cancelParentIds = array();
		foreach ($transactions as $transaction)
		{
			if ($transaction[ValuelinkTransaction::KEY_TRANSACTION_TYPE] == ValuelinkTransaction::CANCEL_TYPE)
			{
				array_push($cancelParentIds, $transaction[ValuelinkTransaction::KEY_PARENT_TRANSACTION_ID]);
			}
		}

		$txns = array();
		foreach ($transactions as $transaction)
		{
			if (
				$transaction[ValuelinkTransaction::KEY_TRANSACTION_TYPE] != ValuelinkTransaction::CANCEL_TYPE &&
				!in_array($transaction[ValuelinkTransaction::KEY_ENTITY_ID], $cancelParentIds)
			){
				array_push($txns, $transaction);
			}
		}

		return $txns;
	}

	public function getUninvoicedSaleAmount($order): float
	{
		$saleAmt = $this->sumOfValuelinkSales($order);
		
		if($saleAmt > 0)
		{
			$existingInvoices = $order->getInvoiceCollection();
			foreach($existingInvoices as $invoice)
			{
				$invoicedSaleAmt = $this->invoiceHelper->getValuelinkSaleBalanceAppliedToInvoice($invoice);
				$saleAmt -= $invoicedSaleAmt;
			}
		}

		return $saleAmt;
	}
}
