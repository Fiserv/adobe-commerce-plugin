<?php
namespace Fiserv\Payments\Model\Source\GooglePay;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonType implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'pay',   'label' => __('Pay')],
            ['value' => 'buy',   'label' => __('Buy')],
            ['value' => 'plain', 'label' => __('Plain')],
        ];
    }
}

