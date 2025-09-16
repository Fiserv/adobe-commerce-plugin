<?php

namespace Fiserv\Payments\Model\Source\PayPal;

use Magento\Framework\Option\ArrayInterface;

class TokenizationStrategy implements ArrayInterface
{
	const ALWAYS = 'ALWAYS';
	const CUSTOMER = 'CUSTOMER';

	/**
	 * Possible PayPal API environments
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		return [
			[
				'value' => self::ALWAYS,
				'label' => __('Tokenize all transactions')
			],
			[
				'value' => self::CUSTOMER,
				'label' => __('Tokenize at customer request')
			]
		];
	}
}
