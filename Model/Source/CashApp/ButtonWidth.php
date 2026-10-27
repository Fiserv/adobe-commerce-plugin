<?php
namespace Fiserv\Payments\Model\Source\CashApp;

use Magento\Framework\Option\ArrayInterface;

class ButtonWidth implements ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'STATIC', 'label' => __('Static')],
			['value' => 'FULL', 'label' => __('Full width')],
		];
	}
}


