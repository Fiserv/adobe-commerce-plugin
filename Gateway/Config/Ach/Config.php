<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\Ach;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class Config
 * 
 * Minimal ACH Gateway Config. Reuses CommerceHub infrastructure.
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	const KEY_ACTIVE = 'payment_active';
	const KEY_PAYMENT_ACTION = 'payment_action';

	const KEY_SDC_PARENT_ELEMENT = 'parent_element';
	const KEY_SDC_PLACEHOLDER = 'placeholder';
	const KEY_SDC_MASK_CHAR = 'masking_character';
	const KEY_SDC_MASK_MODE = 'masking_mode';

	public function __construct(
		ScopeConfigInterface $scopeConfig,
		$methodCode = 'fiserv_ach',
		$pathPattern = self::DEFAULT_PATH_PATTERN
	) {
		parent::__construct($scopeConfig, $methodCode, $pathPattern);
	}

	public function isActive($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
	}

	public function getPaymentAction($storeId = null)
	{
		return $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}

	/**
	 * Get ACH field configuration for routing number
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function routingNumberFormConfig($storeId = null)
	{
		return [
			self::KEY_SDC_PARENT_ELEMENT => $this->getValue('sdc_custom_checkout_routing_number_' . self::KEY_SDC_PARENT_ELEMENT, $storeId),
			self::KEY_SDC_PLACEHOLDER => $this->getValue('sdc_custom_checkout_routing_number_' . self::KEY_SDC_PLACEHOLDER, $storeId),
			self::KEY_SDC_MASK_CHAR => $this->getValue('sdc_custom_checkout_routing_number_' . self::KEY_SDC_MASK_CHAR, $storeId),
			self::KEY_SDC_MASK_MODE => $this->getValue('sdc_custom_checkout_routing_number_' . self::KEY_SDC_MASK_MODE, $storeId),
		];
	}

	/**
	 * Get ACH field configuration for account number
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function accountNumberFormConfig($storeId = null)
	{
		return [
			self::KEY_SDC_PARENT_ELEMENT => $this->getValue('sdc_custom_checkout_account_number_' . self::KEY_SDC_PARENT_ELEMENT, $storeId),
			self::KEY_SDC_PLACEHOLDER => $this->getValue('sdc_custom_checkout_account_number_' . self::KEY_SDC_PLACEHOLDER, $storeId),
			self::KEY_SDC_MASK_CHAR => $this->getValue('sdc_custom_checkout_account_number_' . self::KEY_SDC_MASK_CHAR, $storeId),
			self::KEY_SDC_MASK_MODE => $this->getValue('sdc_custom_checkout_account_number_' . self::KEY_SDC_MASK_MODE, $storeId),
		];
	}

	/**
	 * Get ACH field configuration for ID type
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function idTypeFormConfig($storeId = null)
	{
		return [
			self::KEY_SDC_PARENT_ELEMENT => $this->getValue('sdc_custom_checkout_id_type_' . self::KEY_SDC_PARENT_ELEMENT, $storeId),
			self::KEY_SDC_PLACEHOLDER => $this->getValue('sdc_custom_checkout_id_type_' . self::KEY_SDC_PLACEHOLDER, $storeId),
		];
	}

	/**
	 * Get ACH field configuration for ID value
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function idValueFormConfig($storeId = null)
	{
		return [
			self::KEY_SDC_PARENT_ELEMENT => $this->getValue('sdc_custom_checkout_id_value_' . self::KEY_SDC_PARENT_ELEMENT, $storeId),
			self::KEY_SDC_PLACEHOLDER => $this->getValue('sdc_custom_checkout_id_value_' . self::KEY_SDC_PLACEHOLDER, $storeId),
		];
	}

	/**
	 * Get ACH field configuration for driver license state
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function driverLicenseStateFormConfig($storeId = null)
	{
		return [
			self::KEY_SDC_PARENT_ELEMENT => $this->getValue('sdc_custom_checkout_driver_license_state_' . self::KEY_SDC_PARENT_ELEMENT, $storeId),
			self::KEY_SDC_PLACEHOLDER => $this->getValue('sdc_custom_checkout_driver_license_state_' . self::KEY_SDC_PLACEHOLDER, $storeId),
		];
	}

	/**
	 * Get ACH field configuration for check type
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function checkTypeFormConfig($storeId = null)
	{
		return [
			self::KEY_SDC_PARENT_ELEMENT => $this->getValue('sdc_custom_checkout_check_type_' . self::KEY_SDC_PARENT_ELEMENT, $storeId),
			self::KEY_SDC_PLACEHOLDER => $this->getValue('sdc_custom_checkout_check_type_' . self::KEY_SDC_PLACEHOLDER, $storeId),
		];
	}

	/**
	 * Get ACH field configuration for account type
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function accountTypeFormConfig($storeId = null)
	{
		return [
			self::KEY_SDC_PARENT_ELEMENT => $this->getValue('sdc_custom_checkout_account_type_' . self::KEY_SDC_PARENT_ELEMENT, $storeId),
			self::KEY_SDC_PLACEHOLDER => $this->getValue('sdc_custom_checkout_account_type_' . self::KEY_SDC_PLACEHOLDER, $storeId),
		];
	}

	/**
	 * Get ACH field configuration for business name
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function businessNameFormConfig($storeId = null)
	{
		return [
			self::KEY_SDC_PARENT_ELEMENT => $this->getValue('sdc_custom_checkout_business_name_' . self::KEY_SDC_PARENT_ELEMENT, $storeId),
			self::KEY_SDC_PLACEHOLDER => $this->getValue('sdc_custom_checkout_business_name_' . self::KEY_SDC_PLACEHOLDER, $storeId),
		];
	}
}
