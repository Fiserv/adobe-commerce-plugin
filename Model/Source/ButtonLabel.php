<?php
namespace Fiserv\Payments\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonLabel implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'paypal', 'label' => __('PayPal')],
            ['value' => 'checkout', 'label' => __('Checkout')],
            ['value' => 'pay', 'label' => __('Pay')],
            ['value' => 'buynow', 'label' => __('Buy Now')],
        ];
    }
}
