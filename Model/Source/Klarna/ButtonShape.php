<?php
namespace Fiserv\Payments\Model\Source\Klarna;

use Magento\Framework\Option\ArrayInterface;

class ButtonShape implements ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'default', 'label' => __('Default')],
			['value' => 'rect', 'label' => __('Rect')],
			['value' => 'pill', 'label' => __('Pill')],
		];
	}
}
