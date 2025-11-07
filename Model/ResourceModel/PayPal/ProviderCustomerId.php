<?php
namespace Fiserv\Payments\Model\ResourceModel\PayPal;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ProviderCustomerId extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('fiserv_paypal_customer', 'entity_id');
    }
}
