<?php

namespace Fiserv\Payments\Model\ResourceModel\OpenRefund;

use Fiserv\Payments\Model\OpenRefund;
use Fiserv\Payments\Model\ResourceModel\OpenRefund as OpenRefundResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct()
    {
        $this->_init(OpenRefund::class, OpenRefundResource::class);
    }
}

