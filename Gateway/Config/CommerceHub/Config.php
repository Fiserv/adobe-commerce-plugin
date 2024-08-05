<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\CommerceHub;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	// Gets config values using field names
	const KEY_ACTIVE = 'active';
	const KEY_MERCHANT_ID = 'merchant_id';
	const KEY_TERMINAL_ID = 'terminal_id';
	const KEY_API_KEY = 'api_key';
	const KEY_API_SECRET = 'api_secret';
	const KEY_ENVIRONMENT = 'api_environment';
	const KEY_PAYMENT_TYPE = 'payment_type';
	const KEY_PAYMENT_ACTION = 'payment_action';
	const KEY_CC_TYPES = 'cc_types';
	const KEY_USE_CCV = 'use_ccv';
	const KEY_CURRENCY = 'currency';
	const KEY_CC_TYPES_MAPPER ='cc_types_ch_mapper';
	const KEY_PROD_API_SERVICE = 'prod_api_service';
	const KEY_CERT_API_SERVICE = 'cert_api_service';
	const KEY_PROD_CLIENT_URL = 'prod_client_url';
	const KEY_CERT_CLIENT_URL = 'cert_client_url';
	const KEY_STANDALONE_SPA = 'standalone_spa';
	const KEY_VALUELINK = 'use_valuelink';

	// Iframe Customization Fields
	const KEY_SDC_CUSTOM = 'sdc_custom';
	
	const KEY_SDC_CHECKOUT = 'checkout';
	const KEY_SDC_ADMIN = 'admin';
	const KEY_SDC_TOKEN = 'token';
	
	const KEY_SDC_CARD_NUMBER = 'card_number';
	const KEY_SDC_NAME = 'name_on_card';
	const KEY_SDC_CVV = 'security_code';
	const KEY_SDC_EXP_MONTH = 'expiration_month';
	const KEY_SDC_EXP_YEAR = 'expiration_year';
	
	const KEY_SDC_PARENT_ELEMENT = 'parent_element';
	const KEY_SDC_PLACEHOLDER = 'placeholder';
	const KEY_SDC_PLACEHOLDER_CHAR = 'placeholder_character';
	const KEY_SDC_NUMBER_FORMAT = 'number_formatting';
	const KEY_SDC_MASK = 'masking';
	const KEY_SDC_MASK_CHAR = 'masking_character';
	const KEY_SDC_MASK_MODE = 'masking_mode';
	const KEY_SDC_MASK_LENGTH = 'masking_length';
	const KEY_SDC_OPTION_LABELS = 'option_labels';
	const KEY_SDC_CSS = 'css';
	const KEY_SDC_FONT = 'font';
	const KEY_INVALID_MESSAGE = 'invalid_message';
	const KEY_FONT_DATA = 'data';
	const KEY_FONT_FAMILY = 'family';
	const KEY_FONT_FORMAT = 'format';
	const KEY_FONT_INTEGRITY = 'integrity';

	/**
	 * @var \Magento\Framework\Serialize\Serializer\Json
	 */
	private $serializer;

	/**
	 * Fiserv CommerceHub config constructor
	 *
	 * @param ScopeConfigInterface $scopeConfig
	 * @param null|string $methodCode
	 * @param string $pathPattern
	 * @param Json|null $serializer
	 */
	public function __construct(
		ScopeConfigInterface $scopeConfig,
		$methodCode = null,
		$pathPattern = self::DEFAULT_PATH_PATTERN,
		Json $serializer = null
	) {
		parent::__construct($scopeConfig, $methodCode, $pathPattern);
		$this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()
			->get(Json::class);
	}

	/**
	 * Gets Payment configuration status.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isActive($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
	}

	/**
	 * Returns CommerceHub merchant id.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getMerchantId($storeId = null)
	{
		return $this->getValue(self::KEY_MERCHANT_ID, $storeId);
	}

	/**
	 * Returns CommerceHub terminal id.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getTerminalId($storeId = null)
	{
		return $this->getValue(self::KEY_TERMINAL_ID, $storeId);
	}

	/**
	 * Returns CommerceHub API key.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getApiKey($storeId = null)
	{
		return $this->getValue(self::KEY_API_KEY, $storeId);
	}

	/**
	 * Returns CommerceHub API secret.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getApiSecret($storeId = null)
	{
		return $this->getValue(self::KEY_API_SECRET, $storeId);
	}

	/**
	 * Gets value of CommerceHub API environment.
	 *
	 * Possible values: CERT or PROD.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getApiEnvironment($storeId = null)
	{
		return $this->getValue(self::KEY_ENVIRONMENT, $storeId);
	}

	/**
	 * Gets value of CommerceHub payment action.
	 *
	 * Possible values: Sale or Authorize Only.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getPaymentAction($storeId = null)
	{
		return $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}


	/**
	 * Retrieve available credit card types
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function getAvailableCardTypes($storeId = null)
	{
		$ccTypes = $this->getValue(self::KEY_CC_TYPES, $storeId);

		return !empty($ccTypes) ? explode(',', $ccTypes) : [];
	}

	/**
	 * Checks if CCV field is enabled.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isCcvEnabled($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_USE_CCV, $storeId);
	}

	/**
	 * Gets value of configured currency.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getCurrency($storeId = null)
	{
		return $this->getValue(self::KEY_CURRENCY, $storeId);
	}

	/**
	 * Returns URL of CommerceHub API production service.
	 *
	 * @return string
	 */
	public function getProdApiService()
	{
		return $this->getValue(self::KEY_PROD_API_SERVICE);
	}

	/**
	 * Returns URL of CommerceHub API cert service.
	 *
	 * @return string
	 */
	public function getCertApiService()
	{
		return $this->getValue(self::KEY_CERT_API_SERVICE);
	}

	/**
	 * Returns URL of CommerceHub API production SDK.
	 *
	 * @return string
	 */
	public function getProdClientUrl()
	{
		return $this->getValue(self::KEY_PROD_CLIENT_URL);
	}

	/**
	 * Returns URL of CommerceHub API cert SDK.
	 *
	 * @return string
	 */
	public function getCertClientUrl()
	{
		return $this->getValue(self::KEY_CERT_CLIENT_URL);
	}

	/**
	 * Retrieve mapper between Magento and CommerceHub card types
	 *
	 * @return array
	 */
	public function getCcTypesMapper()
	{
		$result = json_decode(
			$this->getValue(self::KEY_CC_TYPES_MAPPER),
			true
		);

		return is_array($result) ? $result : [];
	}

	/**
	 * Can user store standalone payment accounts
	 *
	 * @param int|null $storeId
	 * @return boolean
	 */
	public function canStandaloneSpa($storeId = null)
	{
		return $this->getValue(self::KEY_STANDALONE_SPA, $storeId);
	}

	/**
	 * Is ValueLink enabled
	 *
	 * @param int|null $storeId
	 * @return boolean
	 */
	public function enableValuelink($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_VALUELINK, $storeId);
	}


	//////////////////////////
	// SDC v2 Customization //
	//////////////////////////
	
	// Checkout
	public function cardNumberFormConfig($formId, $storeId = null)
	{
		if (!$this->validateFormId($formId))
		{
			throw new Exception("Unknown Form Config Id: " . $formId);
		}
		
		$config_path_base = $this->buildConfigPath($formId) . self::KEY_SDC_CARD_NUMBER . '_';
		
		$formConfig = array();
		$formConfig[self::KEY_SDC_PARENT_ELEMENT] = $this->getValue($config_path_base . self::KEY_SDC_PARENT_ELEMENT, $storeId);
		$formConfig[self::KEY_SDC_PLACEHOLDER] = $this->getValue($config_path_base . self::KEY_SDC_PLACEHOLDER, $storeId);
		$formConfig[self::KEY_SDC_PLACEHOLDER_CHAR] = $this->getValue($config_path_base . self::KEY_SDC_PLACEHOLDER_CHAR, $storeId);
		$formConfig[self::KEY_SDC_NUMBER_FORMAT] = $this->getValue($config_path_base . self::KEY_SDC_NUMBER_FORMAT, $storeId);
		$formConfig[self::KEY_SDC_MASK] = $this->getValue($config_path_base . self::KEY_SDC_MASK, $storeId);
		$formConfig[self::KEY_SDC_MASK_CHAR] = $this->getValue($config_path_base . self::KEY_SDC_MASK_CHAR, $storeId);
		$formConfig[self::KEY_SDC_MASK_MODE] = $this->getValue($config_path_base . self::KEY_SDC_MASK_MODE, $storeId);
		$formConfig[self::KEY_SDC_MASK_LENGTH] = $this->getValue($config_path_base . self::KEY_SDC_MASK_LENGTH, $storeId);
		$formConfig[self::KEY_INVALID_MESSAGE] = $this->getValue($config_path_base . self::KEY_INVALID_MESSAGE, $storeId);
		
		return $formConfig;
	}
	public function nameOnCardFormConfig($formId, $storeId = null)
	{
		if (!$this->validateFormId($formId))
		{
			throw new Exception("Unknown Form Config Id: " . $formId);
		}
		
		$config_path_base = $this->buildConfigPath($formId) . self::KEY_SDC_NAME . '_';
		
		$formConfig = array();
		$formConfig[self::KEY_SDC_PARENT_ELEMENT] = $this->getValue($config_path_base . self::KEY_SDC_PARENT_ELEMENT, $storeId);
		$formConfig[self::KEY_SDC_PLACEHOLDER] = $this->getValue($config_path_base . self::KEY_SDC_PLACEHOLDER, $storeId);
		$formConfig[self::KEY_INVALID_MESSAGE] = $this->getValue($config_path_base . self::KEY_INVALID_MESSAGE, $storeId);
		
		return $formConfig;
	}
	public function securityCodeFormConfig($formId, $storeId = null)
	{
		$config_path_base = $this->buildConfigPath($formId) . self::KEY_SDC_CVV . '_';
		
		$formConfig = array();
		$formConfig[self::KEY_SDC_PARENT_ELEMENT] = $this->getValue($config_path_base . self::KEY_SDC_PARENT_ELEMENT, $storeId);
		$formConfig[self::KEY_SDC_PLACEHOLDER] = $this->getValue($config_path_base . self::KEY_SDC_PLACEHOLDER, $storeId);
		$formConfig[self::KEY_SDC_PLACEHOLDER_CHAR] = $this->getValue($config_path_base . self::KEY_SDC_PLACEHOLDER_CHAR, $storeId);
		$formConfig[self::KEY_SDC_MASK] = $this->getValue($config_path_base . self::KEY_SDC_MASK, $storeId);
		$formConfig[self::KEY_SDC_MASK_CHAR] = $this->getValue($config_path_base . self::KEY_SDC_MASK_CHAR, $storeId);
		$formConfig[self::KEY_SDC_MASK_MODE] = $this->getValue($config_path_base . self::KEY_SDC_MASK_MODE, $storeId);
		$formConfig[self::KEY_INVALID_MESSAGE] = $this->getValue($config_path_base . self::KEY_INVALID_MESSAGE, $storeId);
		
		return $formConfig;
	}
	public function expirationMonthFormConfig($formId, $storeId = null)
	{
		if (!$this->validateFormId($formId))
		{
			throw new Exception("Unknown Form Config Id: " . $formId);
		}

		$config_path_base = $this->buildConfigPath($formId) . self::KEY_SDC_EXP_MONTH . '_';
		
		$formConfig = array();
		$formConfig[self::KEY_SDC_PARENT_ELEMENT] = $this->getValue($config_path_base . self::KEY_SDC_PARENT_ELEMENT, $storeId);
		$formConfig[self::KEY_SDC_PLACEHOLDER] = $this->getValue($config_path_base . self::KEY_SDC_PLACEHOLDER, $storeId);
		$formConfig[self::KEY_SDC_OPTION_LABELS] = $this->getValue($config_path_base . self::KEY_SDC_OPTION_LABELS, $storeId);
		$formConfig[self::KEY_INVALID_MESSAGE] = $this->getValue($config_path_base . self::KEY_INVALID_MESSAGE, $storeId);
		
		return $formConfig;
	}
	public function expirationYearFormConfig($formId, $storeId = null)
	{
		if (!$this->validateFormId($formId))
		{
			throw new Exception("Unknown Form Config Id: " . $formId);
		}

		$config_path_base = $this->buildConfigPath($formId) . self::KEY_SDC_EXP_YEAR . '_';
		
		$formConfig = array();
		$formConfig[self::KEY_SDC_PARENT_ELEMENT] = $this->getValue($config_path_base . self::KEY_SDC_PARENT_ELEMENT, $storeId);
		$formConfig[self::KEY_SDC_PLACEHOLDER] = $this->getValue($config_path_base . self::KEY_SDC_PLACEHOLDER, $storeId);
		$formConfig[self::KEY_INVALID_MESSAGE] = $this->getValue($config_path_base . self::KEY_INVALID_MESSAGE, $storeId);
		
		return $formConfig;
	}
	public function cssFormConfig($formId, $storeId = null)
	{
		if (!$this->validateFormId($formId))
		{
			throw new Exception("Unknown Form Config Id: " . $formId);
		}

		$config_path = $this->buildConfigPath($formId) . self::KEY_SDC_CSS;

		return $this->getValue($config_path, $storeId);
	}	
	public function fontFormConfig($formId, $storeId = null)
	{
		if (!$this->validateFormId($formId))
		{
			throw new Exception("Unknown Form Config Id: " . $formId);
		}
		
		$config_path_base = $this->buildConfigPath($formId) . self::KEY_SDC_FONT . '_';
		
		$formConfig = array();
		$formConfig[self::KEY_FONT_DATA] = $this->getValue($config_path_base . self::KEY_FONT_DATA, $storeId);
		$formConfig[self::KEY_FONT_FAMILY] = $this->getValue($config_path_base . self::KEY_FONT_FAMILY, $storeId);
		$formConfig[self::KEY_FONT_FORMAT] = $this->getValue($config_path_base . self::KEY_FONT_FORMAT, $storeId);
		$formConfig[self::KEY_FONT_INTEGRITY] = $this->getValue($config_path_base . self::KEY_FONT_INTEGRITY, $storeId);
		
		return $formConfig;
	}
	
	private function validateFormId($formId)
	{
		return 
			$formId == self::KEY_SDC_CHECKOUT ||
			$formId == self::KEY_SDC_ADMIN ||
			$formId == self::KEY_SDC_TOKEN;
	}
	
	private function buildConfigPath($formId)
	{
		return self::KEY_SDC_CUSTOM . '_' . $formId . '_';
	}
}
