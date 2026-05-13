<?php

declare(strict_types=1);

namespace Fiserv\Payments\Model\ResourceModel\OpenRefund;

use Fiserv\Payments\Model\OpenRefund;
use Fiserv\Payments\Model\ResourceModel\OpenRefund as OpenRefundResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(OpenRefund::class, OpenRefundResource::class);
    }
}

