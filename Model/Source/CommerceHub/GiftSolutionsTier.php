<?php

namespace Fiserv\Payments\Model\Source\CommerceHub;

use Magento\Framework\Option\ArrayInterface;

class GiftSolutionsTier implements ArrayInterface
{
	/**
	 * Return array of options as value-label pairs
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		return [
			['value' => 'basic', 'label' => __('Basic')],
			['value' => 'premium', 'label' => __('Premium')]
		];
	}

	/**
	 * Return options as key-value pairs
	 *
	 * @return array
	 */
	public function toArray()
	{
		return [
			'basic' => __('Basic'),
			'premium' => __('Premium')
		];
	}
}
