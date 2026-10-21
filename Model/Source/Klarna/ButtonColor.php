<?php
namespace Fiserv\Payments\Model\Source\Klarna;

use Magento\Framework\Option\ArrayInterface;

class ButtonColor implements ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'default', 'label' => __('Default')],
			['value' => 'dark', 'label' => __('Dark')],
			['value' => 'light', 'label' => __('Light')],
			['value' => 'outlined', 'label' => __('Outlined')],
		];
	}
}
