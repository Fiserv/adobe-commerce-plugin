<?php
namespace Fiserv\Payments\Model\ResourceModel\PayPal\ProviderCustomerId;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Fiserv\Payments\Model\PayPal\ProviderCustomerId', 'Fiserv\Payments\Model\ResourceModel\PayPal\ProviderCustomerId');
    }
}
