<?php
namespace Fiserv\Payments\Model\Source\CashApp;

use Magento\Framework\Option\ArrayInterface;

class ButtonShape implements ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'ROUND', 'label' => __('Round')],
			['value' => 'SEMIROUND', 'label' => __('Semi-round')],
		];
	}
}


