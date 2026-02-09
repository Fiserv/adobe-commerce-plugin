<?php
namespace Fiserv\Payments\Model\Source\Affirm;

use Magento\Framework\Option\ArrayInterface;

class ButtonColor implements ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'default', 'label' => __('default')],
			['value' => 'light', 'label' => __('light')],
		];
	}
}