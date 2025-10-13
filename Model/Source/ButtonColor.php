<?php
namespace Fiserv\Payments\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonColor implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'gold', 'label' => __('Gold')],
            ['value' => 'blue', 'label' => __('Blue')],
            ['value' => 'silver', 'label' => __('Silver')],
            ['value' => 'white', 'label' => __('White')],
            ['value' => 'black', 'label' => __('Black')],
        ];
    }
}
