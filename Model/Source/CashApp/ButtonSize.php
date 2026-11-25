<?php
namespace Fiserv\Payments\Model\Source\CashApp;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonSize implements OptionSourceInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'MEDIUM', 'label' => __('Medium')],
			['value' => 'SMALL', 'label' => __('Small')],
		];
	}
}
