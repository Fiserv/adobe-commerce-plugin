<?php

namespace Fiserv\Payments\Model\Source\Afterpay;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonShape implements OptionSourceInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'round', 'label' => __('Round')],
			['value' => 'semiround', 'label' => __('Semi')],
		];
	}
}

