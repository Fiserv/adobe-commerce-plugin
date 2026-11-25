<?php
namespace Fiserv\Payments\Model\Source\CashApp;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonTheme implements OptionSourceInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'DARK', 'label' => __('Dark')],
			['value' => 'LIGHT', 'label' => __('Light')],
		];
	}
}
