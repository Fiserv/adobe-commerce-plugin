<?php

namespace Fiserv\Payments\Observer\Valuelink;

use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;
use Magento\Framework\Event\ObserverInterface;


class EnrichValuelinkTransactionsWithOrderId implements ObserverInterface
{
	private $valuelinkResource;

	private $orderHelper;

    /**
     * @param MultiLevelLogger $logger
     */
    public function __construct(
		ValuelinkResource $valuelinkResource,	
		ValuelinkOrderHelper $orderHelper
	) {
		$this->valuelinkResource = $valuelinkResource;
		$this->orderHelper = $orderHelper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
	{
		$order = $observer->getEvent()->getOrder();
		$orderId = $order->getId();
		$incrementId = $order->getIncrementId();

		// Need to filter out canceled transactions as they may be a part of previously failed reorder
		// Thanks Adobe...
		$valuelinkTxns = $this->orderHelper->getValuelinkTransactionsByOrderIncrementId($incrementId);
		$valuelinkTxns = $this->orderHelper->filterCanceledValuelinkTransactions($valuelinkTxns);

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
