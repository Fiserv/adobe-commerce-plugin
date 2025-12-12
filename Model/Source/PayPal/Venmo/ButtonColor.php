<?php
namespace Fiserv\Payments\Model\Source\PayPal\Venmo;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonColor implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'blue', 'label' => __('Blue')],
            ['value' => 'white', 'label' => __('White')],
        ];
    }
}
