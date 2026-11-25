<?php
namespace Fiserv\Payments\Model\Source\CashApp;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonWidth implements OptionSourceInterface
{
	public function toOptionArray()
	{
		return [
			['value' => 'STATIC', 'label' => __('Static')],
			['value' => 'FULL', 'label' => __('Full width')],
		];
	}
}
