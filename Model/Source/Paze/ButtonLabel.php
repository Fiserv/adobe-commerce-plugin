<?php
namespace Fiserv\Payments\Model\Source\Paze;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonLabel implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'checkout', 'label' => __('checkout')],
            ['value' => 'checkout with', 'label' => __('checkout with')],
            ['value' => 'Donate with', 'label' => __('Donate with')],
        ];
    }
}
