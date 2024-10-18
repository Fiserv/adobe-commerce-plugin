<?php

namespace Fiserv\Payments\Model\Valuelink\Helper\Order;

use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Fiserv\Payments\Model\ValuelinkTransaction;

class ValuelinkOrderHelper
{
	private $valuelinkResource;

	public function __construct(
		ValuelinkResource $valuelinkResource
	) {
		$this->valuelinkResource = $valuelinkResource;
	}

	public function getValuelinkTransactions($order)
	{
		return $this->valuelinkResource->getByOrderIncrementId($order->getIncrementId());
	}

	public function getRemainingAuthAmount($order, $txns = null)
	{
		$txns = $txns ?? $this->getValuelinkTransactions($order);

		$authTotal = 0;
		$captureTotal = 0;
		foreach($txns as $txn)
		{
			if ($txn[ValuelinkTransaction::KEY_TRANSACTION_STATE] === ValuelinkTransaction::AUTHORIZED_STATE)
			{
				$authTotal += $txn[ValuelinkTransaction::KEY_AMOUNT];
			}
			elseif ($txn[ValuelinkTransaction::KEY_TRANSACTION_STATE] === ValuelinkTransaction::CAPTURED_STATE)
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
}
