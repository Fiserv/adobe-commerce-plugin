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
		$rawAuths = $this->getUncapturedAuths($rawTxns);
		
		foreach ($rawAuths as $auth)
		{
			$this->valuelinkTransactionManager->cancelValuelinkTransaction($order, $auth);
		}	

        return $this;
	}

	public function getUncapturedAuths($txns)
	{
		$auths = array();
		foreach($txns as $txn)
		{
			if ($txn["transaction_state"] !== ValuelinkTransaction::AUTHORIZED_STATE)
			{
				throw new LocalizedException(__("Cannot cancel order because Valuelink transaction with id: " . $txn["transaction_id"] . " is not in the AUTHORIZED state."));
			}
			
			if ($txn["parent_transaction_id"] !== null)
			{
				throw new LocalizedException(__("Cannot cancel order because Valuelink transaction with id: " . $txn["transaction_id"] . " is a secondary transaction."));
			}
			array_push($auths, $txn);
		}

		return $auths;
	}	
}
