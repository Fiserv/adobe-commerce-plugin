<?php

namespace Fiserv\Payments\Block\Adminhtml\OpenRefund;

use Magento\Backend\Block\Widget\Button;

class CreateButton extends Button
{
    protected function _construct()
    {
        parent::_construct();
        $this->setData([
            'label'   => __('Create New Open Refund'),
            'onclick' => 'setLocation(\'' . $this->getUrl('fiserv/openRefund/edit') . '\')',
            'class'   => 'primary',
        ]);
    }
}

