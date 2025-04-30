<?php
namespace Fiserv\Payments\Controller\Adminhtml\Declines;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Api\FailedOrder\FailedOrderRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Fiserv\Payments\Model\Service\CommerceHub\FailedOrderManager;
use Magento\Framework\App\Action\HttpGetActionInterface;

use Fiserv\Payments\Model\FailedOrder as OrderModel;

class Preview extends Action implements HttpGetActionInterface
{
	private $logger;
	private $jsonFactory;
	private $pageFactory;
	private $failedOrderRepo;
	private $orderRepo;
	private $filterBuilder;
	private $searchBuilder;
	private $resourceConnection;
	private $failedOrderManager;

	public function __construct(
		Context $context,
		MultiLevelLogger $logger,
		JsonFactory $jsonFactory,
		PageFactory $pageFactory,
		FailedOrderRepositoryInterface $failedOrderRepo,
		OrderRepositoryInterface $orderRepo,
		FilterBuilder $filterBuilder,
		SearchCriteriaBuilder $searchBuilder,
		ResourceConnection $resourceConnection,
		FailedOrderManager $failedOrderManager
	) {
		parent::__construct($context);
		$this->logger = $logger;
		$this->jsonFactory = $jsonFactory;
		$this->pageFactory = $pageFactory;
		$this->failedOrderRepo = $failedOrderRepo;
		$this->orderRepo = $orderRepo;
		$this->filterBuilder = $filterBuilder;
		$this->searchBuilder = $searchBuilder;
		$this->resourceConnection = $resourceConnection;
		$this->failedOrderManager = $failedOrderManager;
	}

	public function execute()
	{
		if ($this->getRequest()->isAjax()) {
			$page = (int) $this->getRequest()->getParam('page', 1);
			$pageSize = (int) $this->getRequest()->getParam('pageSize', 5);

			$orders = $this->getOrdersWithDeclines($page, $pageSize);

			$result = $this->jsonFactory->create();
			return $result->setData($orders);
		}

		$resultPage = $this->pageFactory->create();
		$resultPage->setActiveMenu("Fiserv_Payments::failed_transaction_preview");
		$resultPage->getConfig()->getTitle()->prepend(__('UNSUCCESSFUL ORDERS'));

		$orders = $this->getOrdersWithDeclines(1, 5);
		$resultPage->getLayout()->getBlock('failed_transaction_preview')->setData('orders', $orders['orders']);
		$resultPage->getLayout()->getBlock('failed_transaction_preview')->setData('totalPages', $orders['totalPages']);

		return $resultPage;
	}

	private function getOrdersWithDeclines($page, $pageSize)
	{
		$orderIncrementData = $this->getOrderIncrementIdsFromFailedTxns($page, $pageSize);
		$orderIncrementIds = $orderIncrementData['ids'];

		$failedOrders = $this->getFailedOrdersWithDeclines($orderIncrementIds, $page, $pageSize);
		$realOrders = $this->getRealOrdersWithDeclines($orderIncrementIds, $page, $pageSize);
		$failedTransactionData = $this->getFailedTransactionData($orderIncrementIds);

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

		$orderList = array_slice($orderList, ($page - 1) * $pageSize, $pageSize);

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

	private function getFailedOrdersWithDeclines(array $orderIncrementIds)
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

	private function getRealOrdersWithDeclines(array $orderIncrementIds)
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

	private function getOrderIncrementIdsFromFailedTxns($page, $pageSize)
	{
		$connection = $this->resourceConnection->getConnection();
		$offset = ($page - 1) * $pageSize;

		$query = "SELECT DISTINCT order_increment_id FROM failed_transaction WHERE order_increment_id IS NOT NULL ORDER BY order_increment_id DESC  LIMIT $pageSize OFFSET $offset";
		$orderIncrementIds = $connection->fetchCol($query);

		$countQuery = "SELECT COUNT(DISTINCT order_increment_id) FROM failed_transaction WHERE order_increment_id IS NOT NULL";
		$totalCount = $connection->fetchOne($countQuery);

		return ['ids' => $orderIncrementIds, 'count' => $totalCount];
	}

	private function getFailedTransactionData(array $orderIncrementIds)
	{
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
		return [
			OrderModel::KEY_DATE_TIME => $failedOrder[OrderModel::KEY_DATE_TIME],
			OrderModel::KEY_ORDER_INCREMENT_ID => $failedOrder[OrderModel::KEY_ORDER_INCREMENT_ID],
			OrderModel::KEY_CUSTOMER_NAME => $failedOrder[OrderModel::KEY_CUSTOMER_NAME],
			OrderModel::KEY_ORDER_STATE => $failedOrder[OrderModel::KEY_ORDER_STATE],
			OrderModel::KEY_GRAND_TOTAL => $failedOrder[OrderModel::KEY_GRAND_TOTAL]
		];
	}

	private function convertRealOrderToArray($realOrder)
	{
		$failedOrder = $this->failedOrderManager->createFailedOrder($realOrder);
		$failedOrder->setDateTime($realOrder->getData('created_at'));
		return $this->convertFailedOrderToArray($failedOrder);
	}
}

