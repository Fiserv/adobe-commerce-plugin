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
use Fiserv\Payments\Helper\DevMode;

class ApiEnvironment implements ArrayInterface
{
	const ENVIRONMENT_DEV = 'DEV';
	const ENVIRONMENT_QA = 'QA';
	const ENVIRONMENT_CERT = 'CERT';
	const ENVIRONMENT_PROD = 'PROD';

	/**
	 * @var DevMode
	 */
	protected $devMode;

	/**
	 * Constructor
	 *
	 * @param DevMode $devMode
	 */
	public function __construct(DevMode $devMode)
	{
		$this->devMode = $devMode;
	}

	/**
	 * Possible CommerceHub API environments
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		$options = [];
		if ($this->devMode->isDeveloperModeActive()) {
			$options[] = [
				'value' => self::ENVIRONMENT_DEV,
				'label' => __(self::ENVIRONMENT_DEV)
			];
			$options[] = [
				'value' => self::ENVIRONMENT_QA,
				'label' => __(self::ENVIRONMENT_QA)
			];
		}
		$options[] =
			[
				'value' => self::ENVIRONMENT_CERT,
				'label' => __(self::ENVIRONMENT_CERT)
			];
		$options[] = [
			'value' => self::ENVIRONMENT_PROD,
			'label' => __(self::ENVIRONMENT_PROD)
		];
		return $options;
	}
}
