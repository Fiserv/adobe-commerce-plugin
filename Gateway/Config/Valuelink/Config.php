<?php

namespace Fiserv\Payments\Gateway\Config\Valuelink;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	// Gets config values using field names
	const KEY_ACTIVE = 'active';
	const KEY_PAYMENT_ACTION = 'payment_action';
	const KEY_VALUELINK_TITLE = 'valuelink_title';
	const KEY_CARD_AMOUNT_LIMIT = 'card_amount_limit';

	// Iframe Customization Fields
	const KEY_SDC_CUSTOM = 'sdc_custom';
	const KEY_VALUELINK = "valuelink";

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
	 * Retrieve title for Valuelink cards section
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function getValuelinkTitle($storeId = null)
	{
		return $this->getValue(self::KEY_VALUELINK_TITLE, $storeId);
	}

	/**
	 * Get the amount of valuelink cards allowed in a transaction
	 *
	 * @param int|null $storeId
	 * @return int
	 */
	public function getCardAmountLimit($storeId = null)
	{
		return (int) $this->getValue(self::KEY_CARD_AMOUNT_LIMIT, $storeId);
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
		return self::KEY_SDC_CUSTOM . '_' . $formId . '_' . self::KEY_VALUELINK . '_';
	}
}
