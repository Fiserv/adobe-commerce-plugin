<?php
namespace Fiserv\Payments\Model\Source\CashApp;

use Magento\Framework\Option\ArrayInterface;

class ButtonSize implements ArrayInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'MEDIUM', 'label' => __('Medium')],
			['value' => 'SMALL', 'label' => __('Small')],
		];
	}
}


