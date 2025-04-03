<?php

namespace Fiserv\Payments\Model\ResourceModel\FailedTransaction;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Fiserv\Payments\Model\FailedTransaction;
use Fiserv\Payments\Model\ResourceModel\FailedTransaction as FailedTransactionResource;

class Collection extends AbstractCollection
{
	protected function _construct()
	{
		$this->_init(FailedTransaction::class, FailedTransactionResource::class);
	}
}
