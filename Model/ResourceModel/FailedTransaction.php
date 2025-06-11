<?php

namespace Fiserv\Payments\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class FailedTransaction extends AbstractDb
{
	protected function _construct()
	{
		$this->_init('failed_transaction', 'entity_id');  // Define table and primary key
	}

	/**
	 * Fetches a paginated list of all failed transactions.
	 *
	 * @return array The list of failed transactions.
	 */
	public function getFailedTransactionsForPage($pageNum = 1)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
					->from($this->getMainTable())
					->order('entity_id DESC');

		return $connection->fetchAll($select);
	}

	/**
	 * Get records by order increment ID.
	 *
	 * @param string $orderIncrementId
	 * @return array
	 */
	public function getByOrderIncrementId($orderIncrementId)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('order_increment_id = :order_increment_id');

		return $connection->fetchAll($select, ['order_increment_id' => $orderIncrementId]);
	}

	/**
	 * Delete records by order increment ID.
	 *
	 * @param string $orderIncrementId
	 */
	public function deleteByOrderIncrementId($orderIncrementId)
	{
		$connection = $this->getConnection();
		$where = ['order_increment_id = ?' => $orderIncrementId];
		$connection->delete($this->getMainTable(), $where);
	}

	/**
	 * Update order increment ID for specific records.
	 *
	 * @param int[] $ids
	 * @param string $orderIncrementId
	 * @return $this
	 */
	public function updateOrderIncrementId($ids, $orderIncrementId)
	{
		if (empty($ids)) {
			return $this;
		}
		$bind = ['order_increment_id' => $orderIncrementId];
		$where = [$this->getIdFieldName() . ' IN (?)' => $ids];

		$this->getConnection()->update($this->getMainTable(), $bind, $where);
		return $this;
	}

	/**
	 * Get all records.
	 *
	 * @return array
	 */
	public function getFailedTransactions()
	{
		$connection = $this->getConnection();
		$select = $connection->select()
				->from($this->getMainTable())
				->order('entity_id DESC');
		return $connection->fetchAll($select);
	}

	/**
	 * Get records by date and time.
	 *
	 * @param string $dateTime
	 * @return array
	 */
	public function getByDateTime($dateTime)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('date_time = :date_time');

		return $connection->fetchAll($select, ['date_time' => $dateTime]);
	}

	/**
	 * Get records by transaction state
	 *
	 * @param string $transactionState
	 * @return array
	 */
	public function getByTransactionState($transactionState)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('transaction_state = :transaction_state');

		return $connection->fetchAll($select, ['transaction_state' => $transactionState]);
	}

	/**
	 * Get records by approval status
	 *
	 * @param string $approvalStatus
	 * @return array
	 */
	public function getByApprovalStatus($approvalStatus)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('approval_status = :approval_status');

		return $connection->fetchAll($select, ['approval_status' => $approvalStatus]);
	}

	/**
	 * Get records by total amount
	 *
	 * @param float $totalAmount
	 * @return array
	 */
	public function getByTotalAmount($totalAmount)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('total_amount = :total_amount');

		return $connection->fetchAll($select, ['total_amount' => $totalAmount]);
	}

	/**
	 * Get records by remote IP
	 *
	 * @param string $remoteIp
	 * @return array
	 */
	public function getByRemoteIp($remoteIp)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('remote_ip = :remote_ip');

		return $connection->fetchAll($select, ['remote_ip' => $remoteIp]);
	}

	/**
	 * Get records by customer name.
	 *
	 * @param string $customerName
	 * @return array
	 */
	public function getByCustomerName($customerName)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('customer_name = :customer_name');

		return $connection->fetchAll($select, ['customer_name' => $customerName]);
	}

	/**
	 * Get records by processor.
	 *
	 * @param string $processor
	 * @return array
	 */
	public function getByProcessor($processor)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('processor = :processor');

		return $connection->fetchAll($select, ['processor' => $processor]);
	}

	/**
	 * Get records by host.
	 *
	 * @param string $host
	 * @return array
	 */
	public function getByHost($host)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('host = :host');

		return $connection->fetchAll($select, ['host' => $host]);
	}

	/**
	 * Get records by merchant ID.
	 *
	 * @param string $merchantId
	 * @return array
	 */
	public function getByMerchantId($merchantId)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('merchant_id = :merchant_id');

		return $connection->fetchAll($select, ['merchant_id' => $merchantId]);
	}

	/**
	 * Get records by expiration month.
	 *
	 * @param string $expirationMonth
	 * @return array
	 */
	public function getByExpirationMonth($expirationMonth)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('expiration_month = :expiration_month');

		return $connection->fetchAll($select, ['expiration_month' => $expirationMonth]);
	}

	/**
	 * Get records by expiration year.
	 *
	 * @param string $expirationYear
	 * @return array
	 */
	public function getByExpirationYear($expirationYear)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('expiration_year = :expiration_year');

		return $connection->fetchAll($select, ['expiration_year' => $expirationYear]);
	}

	/**
	 * Get records by last 4 digits.
	 *
	 * @param string $last4
	 * @return array
	 */
	public function getByLast4($last4)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('last4 = :last4');

		return $connection->fetchAll($select, ['last4' => $last4]);
	}

	/**
	 * Get records by scheme.
	 *
	 * @param string $scheme
	 * @return array
	 */
	public function getByScheme($scheme)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('scheme = :scheme');

		return $connection->fetchAll($select, ['scheme' => $scheme]);
	}

	/**
	 * Get records by network response code.
	 *
	 * @param string $networkResponseCode
	 * @return array
	 */
	public function getByNetworkResponseCode($networkResponseCode)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('network_response_code = :network_response_code');

		return $connection->fetchAll($select, ['network_response_code' => $networkResponseCode]);
	}

	/**
	 * Get records by bank association details.
	 *
	 * @param string $bankAssociationDetails
	 * @return array
	 */
	public function getByBankAssociationDetails($bankAssociationDetails)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('bank_association_details = :bank_association_details');

		return $connection->fetchAll($select, ['bank_association_details' => $bankAssociationDetails]);
	}

	/**
	 * Get records by bin.
	 *
	 * @param string $bin
	 * @return array
	 */
	public function getByBin($bin)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('bin = :bin');

		return $connection->fetchAll($select, ['bin' => $bin]);
	}

	/**
	 * Get records by transaction ID.
	 *
	 * @param string $txnId
	 * @return array
	 */
	public function getByTxnId($txnId)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('transaction_id = :txn_id');

		return $connection->fetchAll($select, ['txn_id' => $txnId]);
	}

	/**
	 * Get records by currency.
	 *
	 * @param string $currency
	 * @return array
	 */
	public function getByCurrency($currency)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('currency = :currency');

		return $connection->fetchAll($select, ['currency' => $currency]);
	}

	/**
	 * Get records by country.
	 *
	 * @param string $country
	 * @return array
	 */
	public function getByCountry($country)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('country = :country');

		return $connection->fetchAll($select, ['country' => $country]);
	}

	/**
	 * Get records by API Trace ID.
	 *
	 * @param string $apiTraceId
	 * @return array
	 */
	public function getByApiTraceId($apiTraceId)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('api_trace_id = :api_trace_id');

		return $connection->fetchAll($select, ['api_trace_id' => $apiTraceId]);
	}

	/**
	 * Get detailed address information by failed transaction entity ID.
	 *
	 * @param int $entityId
	 * @return array
	 */
	public function getByEntityId($entityId)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('entity_id = :entity_id');

		return $connection->fetchRow($select, ['entity_id' => $entityId]);
	}

	/**
	 * Fetches the total amount of all transactions.
	 *
	 * @return float The total amount.
	 */
	public function fetchTotalAmount()
	{
		$connection = $this->getConnection();
		$select = $connection->select()
				->from($this->getMainTable(), ['COUNT(*) as count', 'SUM(total_amount) as total_amount']);

		return $connection->fetchRow($select);
	}

	/**
	 * Counts the total amount and number of transactions by state.
	 *
	 * @param string $state The transaction state.
	 * @return array The count and total amount of transactions.
	 */
	public function countTotalAmountByState($state)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
				->from($this->getMainTable(), ['COUNT(*) as count', 'SUM(total_amount) as total_amount'])
				->where('transaction_state = ?', $state);

		return $connection->fetchRow($select);
	}

	/**
	 * Counts the total amount and number of DECLINED transactions.
	 *
	 * @return array The count and total amount of DECLINED transactions.
	 */
	public function countDeclinedTransactions()
	{
		return $this->countTotalAmountByState('DECLINED');
	}

	/**
	 * Counts the total amount and number of FRAUD transactions.
	 *
	 * @return array The count and total amount of FRAUD transactions.
	 */
	public function countFraudTransactions()
	{
		return $this->countTotalAmountByState('FRAUD');
	}

	/**
	 * Counts the total amount and number of ERROR transactions.
	 *
	 * @return array The count and total amount of ERROR transactions.
	 */
	public function countErrorTransactions()
	{
		return $this->countTotalAmountByState('ERROR');
	}

	/**
	 * Retrieves a paginated list of failed transactions filtered by order increment ID (required),
	 * and optionally by search term, approval status, transaction state, and date range.
	 * Also returns the total count of matching records.
	 *
	 * @param string      $orderIncrementId  The required order increment ID to filter transactions.
	 * @param int         $page              The current page number for pagination.
	 * @param int         $pageSize          The number of records per page.
	 * @param string|null $search            Optional search keyword to match against multiple fields.
	 * @param string|null $approvalStatus    Optional filter for approval status.
	 * @param string|null $transactionState  Optional filter for transaction state.
	 * @param string|null $fromDate          Optional start date for filtering.
	 * @param string|null $toDate            Optional end date for filtering.
	 *
	 * @return array Returns an array with two keys:
	 *               - 'transactions': array of filtered transaction records.
	 *               - 'count': total number of matching records.
	 */
	public function getFilteredTransactions($orderIncrementId, $page, $pageSize, $search = null, $approvalStatus = null, $transactionState = null, $fromDate = null, $toDate = null)
	{
		$connection = $this->getConnection();
		$count = $page * $pageSize;

		$select = $connection->select()
		       ->from(['ft' => 'failed_transaction'])
		       ->where('ft.order_increment_id = ?', $orderIncrementId)
		       ->order('ft.date_time DESC')
		       ->limit($count, 0);

		if ($search) {
			$search = '%' . trim($search) . '%';
			$searchFields = [
				'ft.transaction_id',
				'ft.remote_ip',
				'ft.approval_status',
				'ft.transaction_state',
			];

			$conditions = [];
			foreach ($searchFields as $field) {
				$conditions[] = $connection->quoteInto("$field LIKE ?", $search);
			}

			$select->where(new \Zend_Db_Expr('(' . implode(' OR ', $conditions) . ')'));
		}

		if (!empty($approvalStatus)) {
			$select->where('ft.approval_status = ?', $approvalStatus);
		}

		if (!empty($transactionState)) {
			$select->where('ft.transaction_state = ?', $transactionState);
		}

		if (!empty($fromDate)) {
			$select->where('DATE(ft.date_time) >= ?', $fromDate);
		}

		if (!empty($toDate)) {
			$select->where('DATE(ft.date_time) <= ?', $toDate);
		}

		$transactions = $connection->fetchAll($select);

		$countSelect = $connection->select()
			    ->from(['ft' => 'failed_transaction'], ['total' => new \Zend_Db_Expr('COUNT(*)')])
			    ->where('ft.order_increment_id = ?', $orderIncrementId);

		if ($search) {
			$conditions = [];
			foreach ($searchFields as $field) {
				$conditions[] = $connection->quoteInto("$field LIKE ?", $search);
			}

			$countSelect->where(new \Zend_Db_Expr('(' . implode(' OR ', $conditions) . ')'));
		}

		if (!empty($approvalStatus)) {
			$countSelect->where('ft.approval_status = ?', $approvalStatus);
		}

		if (!empty($transactionState)) {
			$countSelect->where('ft.transaction_state = ?', $transactionState);
		}

		if (!empty($fromDate)) {
			$countSelect->where('DATE(ft.date_time) >= ?', $fromDate);
		}

		if (!empty($toDate)) {
			$countSelect->where('DATE(ft.date_time) <= ?', $toDate);
		}

		$totalCount = $connection->fetchOne($countSelect);

		return ['transactions' => $transactions, 'count' => $totalCount];
	}
}
