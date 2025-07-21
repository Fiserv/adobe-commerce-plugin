<?php

namespace Fiserv\Payments\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class FailedOrder extends AbstractDb
{
	protected function _construct()
	{
		$this->_init('failed_order', 'entity_id');  // Table name and primary key field
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
}
