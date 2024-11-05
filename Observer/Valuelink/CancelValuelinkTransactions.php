<?php

namespace Fiserv\Payments\Observer\Valuelink;

use Fiserv\Payments\Model\Adapter\Valuelink\ValuelinkCancelRequest;
use Fiserv\Payments\Model\Service\Valuelink\ValuelinkTransactionManager;
use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Fiserv\Payments\Model\ValuelinkTransaction;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Event\ObserverInterface;



class CancelValuelinkTransactions implements ObserverInterface
{
	private $valuelinkResource;
	
	private $valuelinkTransactionManager;
	


    /**
     * @param MultiLevelLogger $logger
     */
    public function __construct(
		ValuelinkResource $valuelinkResource,	
		ValuelinkTransactionManager $valuelinkTransactionManager
	) {
		$this->valuelinkResource = $valuelinkResource;
		$this->valuelinkTransactionManager = $valuelinkTransactionManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
	{
		$payment = $observer->getEvent()->getPayment();
		$order = $payment->getOrder();

		// Cancel command should only cancel Valuelink authorizations
		// Orders being cancelled should ONLY contain uncaptured Valuelink authorizations.
		$rawTxns = $this->valuelinkResource->getByOrderId($order->getId());
		$rawAuths = $this->getAuthsToCancel($rawTxns);
		
		foreach ($rawAuths as $auth)
		{
			$this->valuelinkTransactionManager->cancelValuelinkTransaction($order, $auth);
		}	

        return $this;
	}

	public function getAuthsToCancel($txns)
	{
		$auths = array();
		$cancels = array();
		foreach($txns as $txn)
		{
			if ($txn["transaction_type"] === ValuelinkTransaction::AUTHORIZE_TYPE)
			{
				array_push($auths, $txn);
			}
			else if ($txn["transaction_type"] === ValuelinkTransaction::CANCEL_TYPE)
			{
				array_push($cancels, $txn);
			}
		}
		
		// filter out auths that have already been canceled
		foreach($cancels as $cancel)
		{
			$auths = array_filter($auths, function($txn) use($cancel){ return $txn[ValuelinkTransaction::KEY_ENTITY_ID] != $cancel[ValuelinkTransaction::KEY_PARENT_TRANSACTION_ID]; });
		}

		return $auths;
	}	
}
