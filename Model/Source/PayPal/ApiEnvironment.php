<?php
namespace Fiserv\Payments\Model\Source\PayPal;

use Magento\Framework\Option\ArrayInterface;

class ApiEnvironment implements ArrayInterface
{
    const ENVIRONMENT_QA = 'QA';
	const ENVIRONMENT_CERT = 'CERT';
	const ENVIRONMENT_PROD = 'PROD';

	/**
	 * Possible CommerceHub API environments
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		return [
			[
				'value' => self::ENVIRONMENT_CERT,
				'label' => __(self::ENVIRONMENT_CERT)
			],
			[
				'value' => self::ENVIRONMENT_PROD,
				'label' => __(self::ENVIRONMENT_PROD)
			],
		];
	}
}
