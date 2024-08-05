<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Fiserv
 * @package     Fiserv_Payments
 * @copyright   Copyright (c) 2020 Fiserv, Inc. (http://www.fiserv.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
 
namespace Fiserv\Payments\Model\Source\CommerceHub;

use Magento\Framework\Option\ArrayInterface;

class MaskingModeCvv implements ArrayInterface
{
	const NO_MASK = 'NO_MASKING';
	const ALWAYS_ALL = 'ALWAYS_MASK_ALL';
	const BLUR_ALL = 'BLUR_MASK_ALL';

	/**
	 * Possible CommerceHub API environments
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		return [
			[
				'value' => self::ALWAYS_ALL,
				'label' => __('Mask all always')
			],
			[
				'value' => self::BLUR_ALL,
				'label' => __('Mask all on blur')
			]
		];
	}
}
