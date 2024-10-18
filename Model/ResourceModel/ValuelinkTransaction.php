<?php

namespace Fiserv\Payments\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ValuelinkTransaction extends AbstractDb
{
	protected function _construct()
	{
		$this->_init("valuelink_transaction", "entity_id");
	}

	public function getByOrderIncrementId($orderIncrementId)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('order_increment_id = :order_increment_id');

		return $connection->fetchAll($select, ['order_increment_id' => $orderIncrementId]);
	}

	public function getByOrderId($orderId)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('order_id = :order_id');

		return $connection->fetchAll($select, ['order_id' => $orderId]);
	}

	public function updateOrderId($ids, $orderId)
	{
		if (empty($ids)) {
			return $this;
		}
		$bind = ['order_id' => $orderId];
		$where[$this->getIdFieldName() . ' IN (?)'] = $ids;

		$this->getConnection()->update($this->getMainTable(), $bind, $where);
		return $this;
	}	
	
	public function updateInvoiceId($ids, $invoiceId)
	{
		if (empty($ids)) {
			return $this;
		}
		$bind = ['invoice_id' => $invoiceId];
		$where[$this->getIdFieldName() . ' IN (?)'] = $ids;

		$this->getConnection()->update($this->getMainTable(), $bind, $where);
		return $this;
	}

	public function getByInvoiceId($invoiceId)
	{
		$connection = $this->getConnection();
		$select = $connection->select()
			->from($this->getMainTable())
			->where('invoice_id = :invoice_id');

		return $connection->fetchAll($select, ['invoice_id' => $invoiceId]);
	}
}
