<?php
namespace Fiserv\Payments\Model\Source\CashApp;

use Magento\Framework\Option\ArrayInterface;

class ButtonTheme implements ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'DARK', 'label' => __('Dark')],
			['value' => 'LIGHT', 'label' => __('Light')],
		];
	}
}


