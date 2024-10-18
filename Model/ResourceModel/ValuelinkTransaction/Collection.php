<?php

namespace Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
	protected function _construct()
	{
		$this->_init(
			\Fiserv\Payments\Model\ValuelinkTransaction::class,
			\Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction::class
		);
	}
}
