<?php
namespace Fiserv\Payments\Model\ResourceModel\Subscription;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Order extends AbstractDb
{
	protected function _construct()
	{
		$this->_init('subscription_order', 'entity_id');
	}
}
