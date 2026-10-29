<?php

namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Fiserv\Payments\Model\Service\CommerceHub\FailedOrderManager;
use Fiserv\Payments\Model\PayByLink\PaymentLinkPlacementException;
use Magento\Sales\Model\Order;
use Fiserv\Payments\Logger\MultiLevelLogger;

class OrderPlace
{
	protected $logger;
	protected $failedOrderManager;

	public function __construct(
		MultiLevelLogger $logger,
		FailedOrderManager $failedOrderManager
	) {
		$this->logger = $logger;
		$this->failedOrderManager = $failedOrderManager;
	}

	public function aroundPlace(Order $order, callable $proceed)
	{
		try
		{
			$result = $proceed();
			return $result;
		}
		catch (PaymentLinkPlacementException $e)
		{
			// No order was placed, so there is no failed order to record.
			throw $e;
		}
		catch (\Exception $e)
		{
			$failedOrder = $this->failedOrderManager->createFailedOrder($order);
			$this->failedOrderManager->saveFailedOrder($failedOrder, $order->getQuoteId(), $order->getStoreId());
			throw $e;
		}
	}

}

