<?php

namespace Fiserv\Payments\Model\Source\Afterpay;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonSize implements OptionSourceInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'small', 'label' => __('Small')],
			['value' => 'medium', 'label' => __('Medium')],
			['value' => 'large', 'label' => __('Large')],
		];
	}
}

