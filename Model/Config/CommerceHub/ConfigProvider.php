<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Config\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Fiserv\Payments\Model\Source\CommerceHub\MaskingMode;
use Fiserv\Payments\Model\Source\CommerceHub\MaskingModeCvv;

/**
 * Class ConfigProvider
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_commercehub';
	const VAULT_CODE = 'fiserv_commercehub_vault';
	const VALUELINK_CODE = 'fiserv_valuelink';
	const IS_ACTIVE_KEY = 'isActive';
	const MERCHANT_ID_KEY = 'merchantId';
	const API_KEY_KEY = 'apiKey';
	const CC_MAPPER_KEY = 'ccTypesMapper';
	const ENV_KEY = 'environment';
	const PAYMENT_ACTION_KEY = 'paymentAction';
	const AVAILABLE_CC_KEY = 'availableCardTypes';
	const USE_CCV_KEY = 'useCcv';
	const LOGGING_LEVEL_KEY = 'loggingLevel';
	const CURRENCY_KEY = 'currency';
	const PROD_CLIENT_KEY = 'prodClientUrl';
	const CERT_CLIENT_KEY = 'certClientUrl';
	const VAULT_CODE_KEY = 'vaultCode';
	const CARD_FORM_CONFIG_KEY = 'formConfig';
	const TERMINAL_ID_KEY = 'terminalId';

	const INVALID_FIELDS_KEY = 'invalidFields';
	const CARD_NUMBER_KEY = 'cardNumber';
	const CARD_NAME_KEY = 'nameOnCard';
	const SECURITY_CODE_KEY = 'securityCode';
	const EXP_MONTH_KEY = 'expirationMonth';
	const EXP_YEAR_KEY = 'expirationYear';

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var SessionManagerInterface
	 */
	private $session;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 * @param SessionManagerInterface $session
	 */
	public function __construct(
		Config $config,
		SessionManagerInterface $session
	) {
		$this->config = $config;
		$this->session = $session;
	}

	/**
	 * Retrieve assoc array of checkout configuration
	 *
	 * @return array
	 */
	public function getConfig()
	{
		$storeId = $this->session->getStoreId();
		$config = [
			self::IS_ACTIVE_KEY => $this->config->isActive($storeId),
			self::MERCHANT_ID_KEY => $this->config->getMerchantId($storeId),
			self::API_KEY_KEY => $this->config->getApiKey(),
			self::CC_MAPPER_KEY => $this->config->getCcTypesMapper(),
			self::ENV_KEY => $this->config->getApiEnvironment($storeId),
			self::PAYMENT_ACTION_KEY => $this->config->getPaymentAction($storeId),
			self::AVAILABLE_CC_KEY => $this->config->getAvailableCardTypes($storeId),
			self::USE_CCV_KEY => $this->config->isCcvEnabled($storeId),
			self::LOGGING_LEVEL_KEY => $this->config->getLoggingLevel($storeId),
			self::CURRENCY_KEY => $this->config->getCurrency($storeId),
			self::PROD_CLIENT_KEY => $this->config->getProdClientUrl(),
			self::CERT_CLIENT_KEY => $this->config->getCertClientUrl(),
			self::VAULT_CODE_KEY => self::VAULT_CODE,
			self::CARD_FORM_CONFIG_KEY => $this->buildFormConfig(Config::KEY_SDC_CHECKOUT, $storeId),
			self::TERMINAL_ID_KEY => $this->config->getTerminalId($storeId),
			self::INVALID_FIELDS_KEY => $this->getInvalidFieldMessages(CONFIG::KEY_SDC_CHECKOUT, $storeId)
		];

		return [
			'payment' => [
				self::CODE => $config
			]
		];
	}
	
	public function buildFormConfig($formId, $storeId)
	{
		$fieldsConfig = array();
		$fieldsConfig["fields"] = $this->buildFormFieldsConfig($formId, $storeId);
		$fieldsConfig["css"] = json_decode($this->config->cssFormConfig($formId, $storeId) ?? "{}");
		$fieldsConfig["font"] = $this->config->fontFormConfig($formId, $storeId) ?? array();

		return $fieldsConfig;
	}

	public function buildValuelinkFormConfig($formId, $storeId)
	{
		$fieldsConfig = array();
		$fieldsConfig["fields"] = $this->buildValuelinkFormFieldsConfig($formId, $storeId);
		$fieldsConfig["css"] = json_decode($this->config->cssFormConfig($formId, $storeId, true) ?? "{}");
		$fieldsConfig["font"] = $this->config->fontFormConfig($formId, $storeId, true) ?? array();

		return $fieldsConfig;
	}	

	private function buildValuelinkFormFieldsConfig($formId, $storeId)
	{
		$cardNumberConfig = $this->config->cardNumberFormConfig($formId, $storeId, true);
		$securityCodeConfig = $this->config->securityCodeFormConfig($formId, $storeId, true);
		
		$fieldsConfig = array();
		
		$fieldsConfig["cardNumber"] = array(
			"parentElementId" => $cardNumberConfig[$this->config::KEY_SDC_PARENT_ELEMENT],
			"placeholder" => $cardNumberConfig[$this->config::KEY_SDC_PLACEHOLDER],
			"dynamicPlaceholderCharacter" => $cardNumberConfig[$this->config::KEY_SDC_PLACEHOLDER_CHAR],			
			"enableFormatting" => $cardNumberConfig[$this->config::KEY_SDC_NUMBER_FORMAT] == 1 ? true : false,
			"masking" => array(
				"character" => $cardNumberConfig[$this->config::KEY_SDC_MASK_CHAR],
				"mode" => $cardNumberConfig[$this->config::KEY_SDC_MASK_MODE],
				"shrunkLength" => intval($cardNumberConfig[$this->config::KEY_SDC_MASK_LENGTH])
			)
		);

		if ($cardNumberConfig[$this->config::KEY_SDC_MASK] == 0)
		{
			$fieldsConfig["cardNumber"]["masking"]["mode"] = MaskingMode::NO_MASK;
		}
		
		$fieldsConfig["securityCode"] = array(
			"parentElementId" => $securityCodeConfig[$this->config::KEY_SDC_PARENT_ELEMENT],
			"placeholder" => $securityCodeConfig[$this->config::KEY_SDC_PLACEHOLDER],
			"dynamicPlaceholderCharacter" => $securityCodeConfig[$this->config::KEY_SDC_PLACEHOLDER_CHAR],			
			"masking" => array(
				"character" => $securityCodeConfig[$this->config::KEY_SDC_MASK_CHAR],
				"mode" => $securityCodeConfig[$this->config::KEY_SDC_MASK_MODE],
			)
		);	

		if ($securityCodeConfig[$this->config::KEY_SDC_MASK] == 0)
		{
			$fieldsConfig["securityCode"]["masking"]["mode"] = MaskingModeCvv::NO_MASK;
		}	

		return $fieldsConfig;
	}

	private function buildFormFieldsConfig($formId, $storeId)
	{
		$cardNumberConfig = $this->config->cardNumberFormConfig($formId, $storeId);
		$cardNameConfig = $this->config->nameOnCardFormConfig($formId, $storeId);
		$securityCodeConfig = $this->config->securityCodeFormConfig($formId, $storeId);
		$expMonthConfig = $this->config->expirationMonthFormConfig($formId, $storeId);
		$expYearConfig = $this->config->expirationYearFormConfig($formId, $storeId);
		
		$fieldsConfig = array();
		
		$fieldsConfig["cardNumber"] = array(
			"parentElementId" => $cardNumberConfig[$this->config::KEY_SDC_PARENT_ELEMENT],
			"placeholder" => $cardNumberConfig[$this->config::KEY_SDC_PLACEHOLDER],
			"dynamicPlaceholderCharacter" => $cardNumberConfig[$this->config::KEY_SDC_PLACEHOLDER_CHAR],			
			"enableFormatting" => $cardNumberConfig[$this->config::KEY_SDC_NUMBER_FORMAT] == 1 ? true : false,
			"masking" => array(
				"character" => $cardNumberConfig[$this->config::KEY_SDC_MASK_CHAR],
				"mode" => $cardNumberConfig[$this->config::KEY_SDC_MASK_MODE],
				"shrunkLength" => intval($cardNumberConfig[$this->config::KEY_SDC_MASK_LENGTH])
			)
		);

		if ($cardNumberConfig[$this->config::KEY_SDC_MASK] == 0)
		{
			$fieldsConfig["cardNumber"]["masking"]["mode"] = MaskingMode::NO_MASK;
		}
		
		$fieldsConfig["nameOnCard"] = array(
			"parentElementId" => $cardNameConfig[$this->config::KEY_SDC_PARENT_ELEMENT],
			"placeholder" => $cardNameConfig[$this->config::KEY_SDC_PLACEHOLDER],
	
		);
		
		$fieldsConfig["securityCode"] = array(
			"parentElementId" => $securityCodeConfig[$this->config::KEY_SDC_PARENT_ELEMENT],
			"placeholder" => $securityCodeConfig[$this->config::KEY_SDC_PLACEHOLDER],
			"dynamicPlaceholderCharacter" => $securityCodeConfig[$this->config::KEY_SDC_PLACEHOLDER_CHAR],			
			"masking" => array(
				"character" => $securityCodeConfig[$this->config::KEY_SDC_MASK_CHAR],
				"mode" => $securityCodeConfig[$this->config::KEY_SDC_MASK_MODE],
			)	
		);
		
		if ($securityCodeConfig[$this->config::KEY_SDC_MASK] == 0)
		{
			$fieldsConfig["securityCode"]["masking"]["mode"] = MaskingModeCvv::NO_MASK;
		}

		$fieldsConfig["expirationMonth"] = array(
			"parentElementId" => $expMonthConfig[$this->config::KEY_SDC_PARENT_ELEMENT],
			"placeholder" => $expMonthConfig[$this->config::KEY_SDC_PLACEHOLDER],
			"optionLabels" => json_decode($expMonthConfig[$this->config::KEY_SDC_OPTION_LABELS] ?? "{}"),			
		);
		
		$fieldsConfig["expirationYear"] = array(
			"parentElementId" => $expYearConfig[$this->config::KEY_SDC_PARENT_ELEMENT],
			"placeholder" => $expYearConfig[$this->config::KEY_SDC_PLACEHOLDER],
		);
		
		return $fieldsConfig;
	}

	public function getInvalidFieldMessages($formId, $storeId)
	{
		$cardNumberConfig = $this->config->cardNumberFormConfig($formId, $storeId);
		$cardNameConfig = $this->config->nameOnCardFormConfig($formId, $storeId);
		$securityCodeConfig = $this->config->securityCodeFormConfig($formId, $storeId);
		$expMonthConfig = $this->config->expirationMonthFormConfig($formId, $storeId);
		$expYearConfig = $this->config->expirationYearFormConfig($formId, $storeId);
		
		$messages = array();

		$messages[self::CARD_NUMBER_KEY] = $cardNumberConfig[Config::KEY_INVALID_MESSAGE]; 
		$messages[self::CARD_NAME_KEY] = $cardNameConfig[Config::KEY_INVALID_MESSAGE]; 
		$messages[self::SECURITY_CODE_KEY] = $securityCodeConfig[Config::KEY_INVALID_MESSAGE]; 
		$messages[self::EXP_MONTH_KEY] = $expMonthConfig[Config::KEY_INVALID_MESSAGE]; 
		$messages[self::EXP_YEAR_KEY] = $expYearConfig[Config::KEY_INVALID_MESSAGE]; 

		return $messages;
	}
}
