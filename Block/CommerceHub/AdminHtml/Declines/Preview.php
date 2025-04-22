<?php
namespace Fiserv\Payments\Block\CommerceHub\AdminHtml\Declines;

use Fiserv\Payments\Api\FailedOrder\FailedOrderRepositoryInterface;
use Fiserv\Payments\Model\FailedOrder as OrderModel;
use Fiserv\Payments\Model\FailedTransaction as FailedTransactionModel;
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
	const ADMIN_PANEL_LABEL = 'Admin';

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
		$failedTransactionData = $this->getFailedTransactionData($orderIncrementIds);

		$failedTransactionDataArray = [];
		foreach($failedTransactionData as $ft) {
			$failedTransactionDataArray[$ft[OrderModel::KEY_ORDER_INCREMENT_ID]] = $ft;
		}

		$orderList = array();
		foreach($failedOrders as $fo)
		{
			$orderArray = $this->convertFailedOrderToArray($fo);
			$orderArray[FailedTransactionModel::KEY_REMOTE_IP] = $this->getRemoteIp($failedTransactionDataArray, $fo[OrderModel::KEY_ORDER_INCREMENT_ID] ?? "");
			array_push($orderList, $orderArray); 
		}

		foreach($realOrders as $ro)
		{	
			$orderArray = $this->convertRealOrderToArray($ro);	
			$orderArray[FailedTransactionModel::KEY_REMOTE_IP] = $this->getRemoteIp($failedTransactionDataArray, $ro[OrderModel::KEY_ORDER_INCREMENT_ID] ?? "");
			array_push($orderList, $orderArray);
		}

		usort($orderList, function($a, $b) {
			return strtotime($b[OrderModel::KEY_DATE_TIME]) <=> strtotime($a[OrderModel::KEY_DATE_TIME]);
		});

		return $orderList;
	}

	private function getRemoteIp(Array $failedTxnData, string $orderIncrementId)
	{
		$data = isset($failedTxnData[$orderIncrementId]) ? $failedTxnData[$orderIncrementId] : null;
		return !is_null($data) ? $data[FailedTransactionModel::KEY_REMOTE_IP] : self::ADMIN_PANEL_LABEL;
	}

	private function getFailedOrdersWithDeclines(Array $orderIncrementIds)
	{
		$filter = $this->filterBuilder
			->setField(OrderModel::KEY_ORDER_INCREMENT_ID)
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
		$query = "SELECT DISTINCT order_increment_id FROM failed_transaction WHERE order_increment_id IS NOT NULL";

		return $connection->fetchCol($query);
	}

	private function getFailedTransactionData(Array $orderIncrementIds) {

		$connection = $this->resourceConnection->getConnection();
		$orderIncrementIdsString = implode(',', $orderIncrementIds);
		$query = "
			SELECT ft.order_increment_id, ft.date_time AS oldest_date_time, ft.remote_ip
			FROM failed_transaction ft
			INNER JOIN (
				SELECT order_increment_id, MIN(date_time) AS oldest_date_time
				FROM failed_transaction
				WHERE order_increment_id IN ($orderIncrementIdsString)
				GROUP BY order_increment_id
			) AS subquery
			ON ft.order_increment_id = subquery.order_increment_id AND ft.date_time = subquery.oldest_date_time
		";

		return $connection->fetchAll($query);
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
		$failedOrder->setDateTime($realOrder->getData('created_at'));
		return $this->convertFailedOrderToArray($failedOrder);
	}

	public function formatPrice($amount)
	{
		return $this->pricingHelper->currency($amount, true, false);
	}
}

