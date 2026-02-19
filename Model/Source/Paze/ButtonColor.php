<?php
namespace Fiserv\Payments\Model\Source\Paze;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonColor implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'white', 'label' => __('White')],
            ['value' => 'whitewithoutline', 'label' => __('White Without Line')],
            ['value' => 'midnightblack', 'label' => __('Midnight Black')],
            ['value' => 'pazeblue', 'label' => __('Paze Blue')],
        ];
    }
}
