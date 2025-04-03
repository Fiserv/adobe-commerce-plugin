<?php
namespace Fiserv\Payments\Block\CommerceHub\AdminHtml\Declines;

use Fiserv\Payments\Api\FailedOrder\FailedOrderRepositoryInterface;
use Fiserv\Payments\Model\FailedOrder as OrderModel;
use Fiserv\Payments\Model\Service\CommerceHub\FailedOrderManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

class Preview extends \Magento\Backend\Block\Template
{
	protected $context;

	private $orderRepo;

	private $filterBuilder;

	private $searchBuilder;

	private $resourceConnection;

	private $pricingHelper;

	private $failedOrderRepo;

	private $failedOrderManager;

	const KEY_TRANSACTION = 'transactions';

	public function __construct(
		\Magento\Backend\Block\Template\Context $context,
		OrderRepositoryInterface $orderRepo,
		FilterBuilder $filterBuilder,
		SearchCriteriaBuilder $searchBuilder,
		ResourceConnection $resourceConnection,
		PricingHelper $pricingHelper,
		FailedOrderRepositoryInterface $failedOrderRepo,
		FailedOrderManager $failedOrderManager,
		array $data = []
	) {
		$this->context = $context;
		$this->orderRepo = $orderRepo;
		$this->filterBuilder = $filterBuilder;
		$this->searchBuilder = $searchBuilder;
		$this->resourceConnection = $resourceConnection;
		$this->pricingHelper = $pricingHelper;
		$this->failedOrderRepo = $failedOrderRepo;
		$this->failedOrderManager = $failedOrderManager;
		parent::__construct($context, $data);
	}

	public function getContext()
	{
		return $this->context;
	}

	public function getOrdersWithDeclines()
	{
		$orderIncrementIds = $this->getOrderIncrementIdsFromFailedTxns();
		$failedOrders = $this->getFailedOrdersWithDeclines($orderIncrementIds);
		$realOrders = $this->getRealOrdersWithDeclines($orderIncrementIds);

		$orderList = array();
		foreach($failedOrders as $fo)
		{
			array_push($orderList, $this->convertFailedOrderToArray($fo));
		}
		foreach($realOrders as $ro)
		{	
			array_push($orderList, $this->convertRealOrderToArray($ro));
		}

		usort($orderList, function($a, $b) {
			return intval(strtok($b[OrderModel::KEY_ORDER_INCREMENT_ID], "-")) <=> intval(strtok($a[OrderModel::KEY_ORDER_INCREMENT_ID], "-"));
		});		
		return $orderList;
	}

	private function getFailedOrdersWithDeclines(Array $orderIncrementIds)
	{
		$filter = $this->filterBuilder
			->setField('order_increment_id')
			->setConditionType('in')
			->setValue($orderIncrementIds)
			->create();

		$search = $this->searchBuilder
			->addFilters([$filter])
			->create();

		return $this->failedOrderRepo->getList($search)->getItems();
	}	
		
	private function getRealOrdersWithDeclines(Array $orderIncrementIds)
	{
		$filter = $this->filterBuilder
			->setField('increment_id')
			->setConditionType('in')
			->setValue($orderIncrementIds)
			->create();

		$search = $this->searchBuilder
			->addFilters([$filter])
			->create();

		return $this->orderRepo->getList($search)->getItems();
	}
				
	private function getOrderIncrementIdsFromFailedTxns()
	{
		$connection = $this->resourceConnection->getConnection();
		$query = "SELECT DISTINCT order_increment_id FROM failed_transactions";

		return $connection->fetchCol($query);
	}

	private function convertFailedOrderToArray($failedOrder)
	{
		$arr = array();

		$arr[OrderModel::KEY_DATE_TIME] = $failedOrder[OrderModel::KEY_DATE_TIME];
		$arr[OrderModel::KEY_ORDER_INCREMENT_ID] = $failedOrder[OrderModel::KEY_ORDER_INCREMENT_ID];
		$arr[OrderModel::KEY_CUSTOMER_NAME] = $failedOrder[OrderModel::KEY_CUSTOMER_NAME];
		$arr[OrderModel::KEY_ORDER_STATE] = $failedOrder[OrderModel::KEY_ORDER_STATE];
		$arr[OrderModel::KEY_GRAND_TOTAL] = $failedOrder[OrderModel::KEY_GRAND_TOTAL];

		return $arr;
	}

	private function convertRealOrderToArray($realOrder)
	{
		$failedOrder = $this->failedOrderManager->createFailedOrder($realOrder);
		return $this->convertFailedOrderToArray($failedOrder);	
	}

	public function formatPrice($amount)
	{
		return $this->pricingHelper->currency($amount, true, false);
	}
}

