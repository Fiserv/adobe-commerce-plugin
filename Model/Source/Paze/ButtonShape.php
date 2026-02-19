<?php
namespace Fiserv\Payments\Model\Source\Paze;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonShape implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'default', 'label' => __('Default')],
            ['value' => 'rectangle', 'label' => __('Rectangle')],
            ['value' => 'pill', 'label' => __('Pill')],
        ];
    }
}
