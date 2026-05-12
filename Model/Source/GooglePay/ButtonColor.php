<?php

namespace Fiserv\Payments\Model\Source\GooglePay;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonColor implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'black', 'label' => __('Black')],
            ['value' => 'white', 'label' => __('White')],
        ];
    }
}

