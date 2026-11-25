<?php
namespace Fiserv\Payments\Model\Source\CashApp;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonShape implements OptionSourceInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'ROUND', 'label' => __('Round')],
			['value' => 'SEMIROUND', 'label' => __('Semi-round')],
		];
	}
}
