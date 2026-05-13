<?php

declare(strict_types=1);

namespace Fiserv\Payments\Model;

use Magento\Framework\Model\AbstractModel;

class OpenRefund extends AbstractModel
{
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_DECLINED = 'DECLINED';
    public const STATUS_ERROR = 'ERROR';

    protected function _construct(): void
    {
        $this->_init(\Fiserv\Payments\Model\ResourceModel\OpenRefund::class);
    }
}

