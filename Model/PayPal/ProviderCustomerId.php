<?php
namespace Fiserv\Payments\Model\PayPal;

use Magento\Framework\Model\AbstractModel;

class ProviderCustomerId extends AbstractModel
{
    protected function _construct()
    {
        $this->_init('Fiserv\Payments\Model\ResourceModel\PayPal\ProviderCustomerId');
    }
}
