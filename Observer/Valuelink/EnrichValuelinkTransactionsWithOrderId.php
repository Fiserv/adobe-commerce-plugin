<?php

namespace Fiserv\Payments\Observer\Valuelink;

use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Magento\Framework\Event\ObserverInterface;


class EnrichValuelinkTransactionsWithOrderId implements ObserverInterface
{
	private $valuelinkResource;



    /**
     * @param MultiLevelLogger $logger
     */
    public function __construct(
		ValuelinkResource $valuelinkResource,	

	) {
		$this->valuelinkResource = $valuelinkResource;

    }

    public function execute(\Magento\Framework\Event\Observer $observer)
	{
		$order = $observer->getEvent()->getOrder();
		$orderId = $order->getId();
		$incrementId = $order->getIncrementId();

		$valuelinkTxns = $this->valuelinkResource->getByOrderIncrementId($incrementId);

		if (!empty($valuelinkTxns))
		{
			$txnIds = array();

			foreach ($valuelinkTxns as $txn)
			{
				array_push($txnIds, $txn["entity_id"]);
			}

			$this->valuelinkResource->updateOrderId($txnIds, $orderId);
		}

        return $this;
    }
}
