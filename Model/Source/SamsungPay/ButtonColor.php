<?php
namespace Fiserv\Payments\Model\Source\SamsungPay;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonColor implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'white', 'label' => __('White')],
            ['value' => 'black', 'label' => __('Black')],
        ];
    }
}
