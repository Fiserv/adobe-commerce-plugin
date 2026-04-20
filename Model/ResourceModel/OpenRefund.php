<?php

namespace Fiserv\Payments\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class OpenRefund extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('fiserv_open_refund', 'entity_id');
    }
}

