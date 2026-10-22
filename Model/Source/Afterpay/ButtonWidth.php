<?php

namespace Fiserv\Payments\Model\Source\Afterpay;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonWidth implements OptionSourceInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'static', 'label' => __('Static')],
			['value' => 'full', 'label' => __('Full')],
		];
	}
}

