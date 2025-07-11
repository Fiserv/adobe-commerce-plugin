<?php

namespace Fiserv\Payments\Helper;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\App\ResourceConnection;
use Fiserv\Payments\Model\Service\CommerceHub\FailedOrderManager;
use Fiserv\Payments\Helper\DeclinedRealOrdersHelper;
use Fiserv\Payments\Model\FailedTransactionRepository;
use Fiserv\Payments\Model\FailedOrderRepository;
use Fiserv\Payments\Model\FailedOrder as OrderModel;
use Magento\Framework\UrlInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

class DeclinedOrdersHelper
{
	private $logger;
	private $resourceConnection;
	private $failedOrderManager;
	private $failedTransactionRepo;
	private $failedOrderRepo;
	private $declinedRealOrderHelper;
	private $urlBuilder;
	private $pricingHelper;
	const ORDER_URL = 'orderViewUrl';

	public function __construct(
		MultiLevelLogger $logger,
		ResourceConnection $resourceConnection,
		FailedOrderManager $failedOrderManager,
		FailedTransactionRepository $failedTransactionRepo,
		FailedOrderRepository $failedOrderRepo,
		DeclinedRealOrdersHelper $declinedRealOrderHelper,
		UrlInterface $urlBuilder,
		PricingHelper $pricingHelper
	) {
		$this->logger = $logger;
		$this->resourceConnection = $resourceConnection;
		$this->failedOrderManager = $failedOrderManager;
		$this->failedTransactionRepo = $failedTransactionRepo;
		$this->failedOrderRepo = $failedOrderRepo;
		$this->declinedRealOrderHelper = $declinedRealOrderHelper;
		$this->urlBuilder = $urlBuilder;
		$this->pricingHelper = $pricingHelper;
	}

	public function getOrdersWithDeclines($page = 1, $pageSize = 20, $search = '', $approvalStatus = '', $orderState = '', $fromDate = null, $toDate = null, $type = 'orders')
	{
		$orderIncrementData = $this->getOrderIncrementIdsFromFailedTxns($page, $pageSize, $search, $approvalStatus, $orderState, $fromDate, $toDate);
		$orderIncrementIds = $orderIncrementData['ids'];

		$allApprovalStatus = $this->failedTransactionRepo->getAllAprovalStatus();
		$allOrderState = $this->failedOrderRepo->getAllOrderState();

		$failedTransactionDataArray = [];
		$orderTransactionDates = [];
		$orderList = [];
		$processedOrderIds = [];

		if ($type === 'orders' || $type === 'all') {
			$failedOrders = $this->failedOrderRepo->getFailedOrdersWithDeclines($orderIncrementIds);
			$realOrders = $this->declinedRealOrderHelper->getRealOrdersWithDeclines($orderIncrementIds);

			$failedTransactionData = $this->failedTransactionRepo->getFailedTransactionData($orderIncrementIds);
			foreach ($failedTransactionData as $ft) {
				$failedTransactionDataArray[$ft['order_increment_id']] = $ft;
			}

			foreach ($failedOrders as $fo) {
				$orderArray = $this->convertFailedOrderToArray($fo);
				$orderIncrementId = $orderArray['order_increment_id'];
				$orderArray['remote_ip'] = $this->getRemoteIp($failedTransactionDataArray, $orderIncrementId ?? '');
				$orderArray['approval_status'] = $failedTransactionDataArray[$orderIncrementId]['approval_status'];
				array_push($orderList, $orderArray);
				$processedOrderIds[] = $orderIncrementId;

				// Fetch transactions and find latest date
				$transactions = $this->failedTransactionRepo->getListByOrderIncrementId($orderIncrementId);
				$latestTransactionDate = null;
				foreach ($transactions as $transaction) {
					$date = $transaction->getDateTime();
					if ($latestTransactionDate === null || strtotime($date) > strtotime($latestTransactionDate)) {
						$latestTransactionDate = $date;
					}
				}
				$orderTransactionDates[$orderIncrementId] = $latestTransactionDate;
			}

			foreach ($realOrders as $ro) {
				$incrementId = $ro['increment_id'];
				if (in_array($incrementId, $processedOrderIds)) {
					continue;
				}
				$orderArray = $this->convertRealOrderToArray($ro);
				$orderArray['remote_ip'] = $this->getRemoteIp($failedTransactionDataArray, $incrementId ?? '');
				$orderArray['approval_status'] = $failedTransactionDataArray[$incrementId]['approval_status'];
				array_push($allOrderState, $orderArray['order_state']);
				array_push($orderList, $orderArray);

				// Fetch transactions and find latest date
				$transactions = $this->failedTransactionRepo->getListByOrderIncrementId($incrementId);
				$latestTransactionDate = null;
				foreach ($transactions as $transaction) {
					$date = $transaction->getDateTime();
					if ($latestTransactionDate === null || strtotime($date) > strtotime($latestTransactionDate)) {
						$latestTransactionDate = $date;
					}
				}
				$orderTransactionDates[$incrementId] = $latestTransactionDate;
			}
		}

		if ($type === 'transactions' || $type === 'all') {
			$failedTransactionData = $this->failedTransactionRepo->getFailedTransactionData($orderIncrementIds);
			foreach ($failedTransactionData as $transaction) {
				$transactionArray = $this->convertFailedTransactionToArray($transaction); // Implement this method to convert transaction data into desired format
				array_push($orderList, $transactionArray);
				$processedOrderIds[] = $transaction['order_increment_id'];
			}
		}

		// Sort orders by the date of their latest transaction
		usort($orderList, function ($a, $b) use ($orderTransactionDates) {
			$latestDateA = strtotime($orderTransactionDates[$a[OrderModel::KEY_ORDER_INCREMENT_ID]]);
			$latestDateB = strtotime($orderTransactionDates[$b[OrderModel::KEY_ORDER_INCREMENT_ID]]);
			return $latestDateB <=> $latestDateA;
		});

		$allOrderState = array_values(array_unique(array_map('strtoupper', $allOrderState)));
		sort($allOrderState);

		return [
			'orders' => $orderList,
			'totalPages' => ceil($orderIncrementData['count'] / $pageSize),
			'approval_status' => $allApprovalStatus,
			'order_state' => $allOrderState
		];
	}

	private function getRemoteIp(array $failedTxnData, string $orderIncrementId)
	{
		$data = $failedTxnData[$orderIncrementId] ?? null;
		return $data ? $data['remote_ip'] : 'Admin';
	}

	private function getOrderIncrementIdsFromFailedTxns($page, $pageSize, $search = null, $approvalStatus = null, $orderState = null, $fromDate = null, $toDate = null)
	{
		$connection = $this->resourceConnection->getConnection();
		$offset = ($page - 1) * $pageSize;

		$select = $connection->select()
			->distinct(true)
			->from(['ft' => 'failed_transaction'], ['order_increment_id'])
			->joinLeft(['so' => 'sales_order'], 'ft.order_increment_id = so.increment_id', [])
			->joinLeft(['fo' => 'failed_order'], 'ft.order_increment_id = fo.order_increment_id', [])
			->where('ft.order_increment_id IS NOT NULL')
			->order('ft.order_increment_id DESC')
			->limit($pageSize, $offset);

		if ($search) {
			$search = '%' . trim($search) . '%';
			$searchFields = [
				'so.increment_id',
				'so.customer_firstname',
				'so.customer_lastname',
				'so.status',
				'so.grand_total',
				'fo.order_increment_id',
				'fo.customer_name',
				'fo.order_state',
				'fo.grandTotal',
				'ft.remote_ip',
				'ft.approval_status'
			];

			$conditions = [];
			foreach ($searchFields as $field) {
				$conditions[] = $connection->quoteInto("$field LIKE ?", $search);
			}

			$select->where(new \Zend_Db_Expr('(' . implode(' OR ', $conditions) . ')'));
		}

		if ($approvalStatus !== null && $approvalStatus !== '') {
			$select->where('ft.approval_status = ?', $approvalStatus);
		}

		if ($orderState !== null && $orderState !== '') {
			$select->where(
				new \Zend_Db_Expr('COALESCE(fo.order_state, so.status) = ' . $connection->quote($orderState))
			);
		}

		if ($fromDate) {
			$select->where('DATE(ft.date_time) >= ?', $fromDate);
		}

		if ($toDate) {
			$select->where('DATE(ft.date_time) <= ?', $toDate);
		}

		$orderIncrementIds = $connection->fetchCol($select);

		$countSelect = $connection->select()
			->from(['ft' => 'failed_transaction'], ['total' => new \Zend_Db_Expr('COUNT(DISTINCT ft.order_increment_id)')])
			->joinLeft(['so' => 'sales_order'], 'ft.order_increment_id = so.increment_id', [])
			->joinLeft(['fo' => 'failed_order'], 'ft.order_increment_id = fo.order_increment_id', [])
			->where('ft.order_increment_id IS NOT NULL');

		if ($search) {
			$conditions = [];
			foreach ($searchFields as $field) {
				$conditions[] = $connection->quoteInto("$field LIKE ?", $search);
			}

			$countSelect->where(new \Zend_Db_Expr('(' . implode(' OR ', $conditions) . ')'));
		}

		if ($approvalStatus !== null && $approvalStatus !== '') {
			$countSelect->where('ft.approval_status = ?', $approvalStatus);
		}

		if ($orderState !== null && $orderState !== '') {
			$countSelect->where(
				new \Zend_Db_Expr('COALESCE(fo.order_state, so.status) = ' . $connection->quote($orderState))
			);
		}

		if ($fromDate) {
			$countSelect->where('DATE(ft.date_time) >= ?', $fromDate);
		}

		if ($toDate) {
			$countSelect->where('DATE(ft.date_time) <= ?', $toDate);
		}

		$totalCount = $connection->fetchOne($countSelect);

		return ['ids' => $orderIncrementIds, 'count' => $totalCount];
	}

	private function convertFailedOrderToArray($failedOrder)
	{
		return [
			OrderModel::KEY_DATE_TIME => $failedOrder[OrderModel::KEY_DATE_TIME],
			OrderModel::KEY_ORDER_INCREMENT_ID => $failedOrder[OrderModel::KEY_ORDER_INCREMENT_ID],
			OrderModel::KEY_CUSTOMER_NAME => $failedOrder[OrderModel::KEY_CUSTOMER_NAME],
			OrderModel::KEY_ORDER_STATE => strtoupper($failedOrder[OrderModel::KEY_ORDER_STATE]),
			OrderModel::KEY_GRAND_TOTAL => $this->formatPrice($failedOrder[OrderModel::KEY_GRAND_TOTAL]),
			self::ORDER_URL => $this->urlBuilder->getUrl('fiserv/declines/order', ['id' => $failedOrder[OrderModel::KEY_ORDER_INCREMENT_ID]])
		];
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
