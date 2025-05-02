<?php

namespace Fiserv\Payments\Helper;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\App\ResourceConnection;
use Fiserv\Payments\Model\Service\CommerceHub\FailedOrderManager;
use Fiserv\Payments\Helper\DeclinedRealOrdersHelper;
use Fiserv\Payments\Model\FailedTransactionRepository;
use Fiserv\Payments\Model\FailedOrderRepository;

class DeclinedOrdersHelper
{
	private $logger;
	private $resourceConnection;
	private $failedOrderManager;
	private $failedTransactionRepo;
	private $failedOrderRepo;
	const ORDER_URL = 'orderViewUrl';

	public function __construct(
		MultiLevelLogger $logger,
		ResourceConnection $resourceConnection,
		FailedOrderManager $failedOrderManager,
		FailedTransactionRepository $failedTransactionRepo,
		FailedOrderRepository $failedOrderRepo
	) {
		$this->logger = $logger;
		$this->resourceConnection = $resourceConnection;
		$this->failedOrderManager = $failedOrderManager;
		$this->failedTransactionRepo = $failedTransactionRepo;
		$this->failedOrderRepo = $failedOrderRepo;
	}

	public static function getOrdersWithDeclines($page=1, $pageSize=5, $search='')
	{
		$orderIncrementData = $this->getOrderIncrementIdsFromFailedTxns($page, $pageSize, $search);
		$orderIncrementIds = $orderIncrementData['ids'];

		$failedOrders = $this->failedOrderRepo->getFailedOrdersWithDeclines($orderIncrementIds);
		$realOrders = DeclinedRealOrdersHelper::getRealOrdersWithDeclines($orderIncrementIds);
		$failedTransactionData = $this->failedTransactionRepo->getFailedTransactionData($orderIncrementIds);

		$failedTransactionDataArray = [];
		foreach ($failedTransactionData as $ft) {
			$failedTransactionDataArray[$ft['order_increment_id']] = $ft;
		}

		$orderList = [];
		foreach ($failedOrders as $fo) {
			$orderArray = $this->convertFailedOrderToArray($fo);
			$orderArray['remote_ip'] = $this->getRemoteIp($failedTransactionDataArray, $fo['order_increment_id'] ?? "");
			array_push($orderList, $orderArray);
		}

		foreach ($realOrders as $ro) {
			$orderArray = $this->convertRealOrderToArray($ro);
			$orderArray['remote_ip'] = $this->getRemoteIp($failedTransactionDataArray, $ro['order_increment_id'] ?? "");
			array_push($orderList, $orderArray);
		}

		usort($orderList, function ($a, $b) {
			return strtotime($b['date_time']) <=> strtotime($a['date_time']);
		});

		return [
			'orders' => $orderList,
			'totalPages' => ceil($orderIncrementData['count'] / $pageSize)
		];
	}
	
	private function getRemoteIp(array $failedTxnData, string $orderIncrementId)
	{
		$data = $failedTxnData[$orderIncrementId] ?? null;
		return $data ? $data['remote_ip'] : 'Admin';
	}
	
	private function getOrderIncrementIdsFromFailedTxns($page, $pageSize, $search = null)
	{
		$connection = $this->resourceConnection->getConnection();
		$offset = ($page - 1) * $pageSize;

		$searchCondition = '';
		if ($search) {
			$searchCondition = "AND (
				sales_order.increment_id LIKE '%$search%' OR
				sales_order.customer_firstname LIKE '%$search%' OR
				sales_order.customer_lastname LIKE '%$search%' OR
				sales_order.status LIKE '%$search%' OR
				sales_order.grand_total LIKE '%$search%' OR
				failed_order.order_increment_id LIKE '%$search%' OR
				failed_order.customer_name LIKE '%$search%' OR
				failed_order.order_state LIKE '%$search%' OR
				failed_order.grandTotal LIKE '%$search%' OR
				failed_transaction.remote_ip LIKE '%$search%'
			)";
		}

		$query = "SELECT DISTINCT failed_transaction.order_increment_id
			FROM failed_transaction
			LEFT JOIN sales_order ON failed_transaction.order_increment_id = sales_order.increment_id
			LEFT JOIN failed_order ON failed_transaction.order_increment_id = failed_order.order_increment_id
			WHERE failed_transaction.order_increment_id IS NOT NULL $searchCondition
			ORDER BY failed_transaction.order_increment_id DESC
			LIMIT $pageSize OFFSET $offset";
		$orderIncrementIds = $connection->fetchCol($query);

		$countQuery = "SELECT COUNT(DISTINCT failed_transaction.order_increment_id)
			FROM failed_transaction
			LEFT JOIN sales_order ON failed_transaction.order_increment_id = sales_order.increment_id
			LEFT JOIN failed_order ON failed_transaction.order_increment_id = failed_order.order_increment_id
			WHERE failed_transaction.order_increment_id IS NOT NULL $searchCondition";
		$totalCount = $connection->fetchOne($countQuery);

		return ['ids' => $orderIncrementIds, 'count' => $totalCount];
	}
	
	private function convertFailedOrderToArray($failedOrder)
	{
		return [
			OrderModel::KEY_DATE_TIME => $failedOrder[OrderModel::KEY_DATE_TIME],
			OrderModel::KEY_ORDER_INCREMENT_ID => $failedOrder[OrderModel::KEY_ORDER_INCREMENT_ID],
			OrderModel::KEY_CUSTOMER_NAME => $failedOrder[OrderModel::KEY_CUSTOMER_NAME],
			OrderModel::KEY_ORDER_STATE => $failedOrder[OrderModel::KEY_ORDER_STATE],
			OrderModel::KEY_GRAND_TOTAL => $failedOrder[OrderModel::KEY_GRAND_TOTAL],
			self::ORDER_URL => $this->getUrl('fiserv/declines/order', ['id' => $failedOrder[OrderModel::KEY_ORDER_INCREMENT_ID]])
		];
	}

	private function convertRealOrderToArray($realOrder)
	{
		$failedOrder = $this->failedOrderManager->createFailedOrder($realOrder);
		$failedOrder->setDateTime($realOrder->getData('created_at'));
		return $this->convertFailedOrderToArray($failedOrder);
	}
}