<?php

declare(strict_types=1);

namespace Fiserv\Payments\Model;

use Magento\Framework\Model\AbstractModel;

class OpenRefund extends AbstractModel
{
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_DECLINED = 'DECLINED';

    public const KEY_ENTITY_ID           = 'entity_id';
    public const KEY_OPEN_REFUND_ID      = 'open_refund_id';
    public const KEY_AMOUNT              = 'amount';
    public const KEY_CURRENCY_CODE       = 'currency_code';
    public const KEY_CUSTOMER_ID         = 'customer_id';
    public const KEY_REFUND_REASON       = 'refund_reason';
    public const KEY_NOTES               = 'notes';
    public const KEY_ORDER_ID            = 'order_id';
    public const KEY_CASE_NUMBER         = 'case_number';
    public const KEY_ADMIN_USER_ID       = 'admin_user_id';
    public const KEY_ADMIN_USERNAME      = 'admin_username';
    public const KEY_LINKED_IDENTIFIER   = 'linked_identifier';
    public const KEY_STATUS              = 'status';
    public const KEY_CH_REFUND_ID        = 'commercehub_refund_id';
    public const KEY_ERROR_MESSAGE       = 'error_message';
    public const KEY_CREATED_AT          = 'created_at';
    public const KEY_UPDATED_AT          = 'updated_at';

    protected function _construct(): void
    {
        $this->_init(\Fiserv\Payments\Model\ResourceModel\OpenRefund::class);
    }
}

