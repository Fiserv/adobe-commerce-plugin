<?php

namespace Fiserv\Payments\Model\ResourceModel\FailedOrder;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Fiserv\Payments\Model\FailedOrder;
use Fiserv\Payments\Model\ResourceModel\FailedOrder as FailedOrderResource;

class Collection extends AbstractCollection
{
	protected function _construct()
	{
		$this->_init(FailedOrder::class, FailedOrderResource::class);
	}
}
