<?php
namespace Fiserv\Payments\Model\Source\Klarna;

use Magento\Framework\Option\ArrayInterface;

class LogoAlignment implements ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'default', 'label' => __('Default')],
			['value' => 'left', 'label' => __('Left')],
			['value' => 'center', 'label' => __('Center')],
		];
	}
}
