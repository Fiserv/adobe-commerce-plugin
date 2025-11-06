<?php
namespace Fiserv\Payments\Model\Source\ApplePay;

use Magento\Framework\Option\ArrayInterface;

class ButtonStyle implements ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'black', 'label' => __('Black')],
			['value' => 'white', 'label' => __('White')],
			['value' => 'white-outline', 'label' => __('White Outline')],
		];
	}
}