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
 * @category	Fiserv
 * @package	Fiserv_Payments
 * @copyright	Copyright (c) 2020 Fiserv, Inc. (http://www.fiserv.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Fiserv\Payments\Model\Source\CommerceHub;

use Magento\Framework\App\Filesystem\DirectoryList;

class FiservCountry extends \Magento\Directory\Model\Config\Source\Country
{
	/**
	 * @var array
	 */
	private $allowed;

	protected $moduleReader;

	/**
	 * @param \Magento\Directory\Model\ResourceModel\Country\Collection $countryCollection
	 */
	public function __construct(
		\Magento\Directory\Model\ResourceModel\Country\Collection $countryCollection, 
		\Magento\Framework\Module\Dir\Reader $moduleReader)
	{
		parent::__construct($countryCollection);
		$this->moduleReader = $moduleReader;
		$dirpath = $this->moduleReader->getModuleDir(
			\Magento\Framework\Module\Dir::MODULE_ETC_DIR,
			"Fiserv_Payments"
		);
		$fullpath = $dirpath.'/country_codes.txt';
		$this->allowed = file($fullpath, FILE_IGNORE_NEW_LINES);
	}

	/**
	 * Return options array
	 *
	 * @param boolean $isMultiSelect
	 * @param string|array $foregroundCountries
	 * @return array
	 */
	public function toOptionArray($isMultiselect = false, $foregroundCountries = '')
	{
		$options = parent::toOptionArray($isMultiselect, $foregroundCountries);

		for ($i = count($options)-1; $i >= 0; $i--)
		{
			if (!in_array($options[$i]['value'], $this->allowed))
			{
			unset($options[$i]);
			}
		}

 
		return $options;
	}
}
