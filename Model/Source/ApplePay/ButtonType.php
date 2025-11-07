<?php
namespace Fiserv\Payments\Model\Source\ApplePay;

use Magento\Framework\Option\ArrayInterface;

class ButtonType implements ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'add-money', 'label' => __('Add Money')],
			['value' => 'book', 'label' => __('Book')],
			['value' => 'buy', 'label' => __('Buy')],
			['value' => 'check-out', 'label' => __('Checkout')],
			['value' => 'continue', 'label' => __('Continue')],
			['value' => 'contribute', 'label' => __('Contribute')],
			['value' => 'donate', 'label' => __('Donate')],
			['value' => 'order', 'label' => __('Order')],
			['value' => 'pay', 'label' => __('Pay')],
			['value' => 'plain', 'label' => __('Plain')],
			['value' => 'reload', 'label' => __('Reload')],
			['value' => 'rent', 'label' => __('Rent')],
			['value' => 'set-up', 'label' => __('Set Up')],
			['value' => 'subscribe', 'label' => __('Subscribe')],
			['value' => 'support', 'label' => __('Support')],
			['value' => 'tip', 'label' => __('Tip')],
			['value' => 'top-up', 'label' => __('Top Up')]
		];
	}
}
