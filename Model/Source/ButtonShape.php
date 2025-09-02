<?php
namespace Fiserv\Payments\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonShape implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'rect', 'label' => __('Rectangle')],
            ['value' => 'pill', 'label' => __('Pill')],
        ];
    }
}
