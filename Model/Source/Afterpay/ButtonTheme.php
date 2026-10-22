<?php

namespace Fiserv\Payments\Model\Source\Afterpay;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonTheme implements OptionSourceInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'mint', 'label' => __('Mint')],
			['value' => 'black', 'label' => __('Black')],
			['value' => 'white', 'label' => __('White')],
		];
	}
}

