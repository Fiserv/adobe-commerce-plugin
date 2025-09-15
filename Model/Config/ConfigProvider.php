<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Config;

use Fiserv\Payments\Gateway\Config\Config;
use Fiserv\Payments\Gateway\Config\Valuelink\Config as ValuelinkConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Session\SessionManagerInterface;
use \Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Model\Source\CommerceHub\MaskingMode;
use Fiserv\Payments\Model\Source\CommerceHub\MaskingModeCvv;

/**
 * Class ConfigProvider
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_payments';
	const IS_ACTIVE_KEY = 'isActive';
	const STORE_URL_KEY = 'storeUrl';
	const STORE_ID_KEY = 'storeId';
	const WEBSITE_ID_KEY = "websiteId";
	const FISERV_VALUELINK_KEY = "fiserv_valuelink";
	const VALUELINK_TITLE_KEY = "valuelink_title";
	const VALUELINK_FORM_CONFIG_KEY = "valuelinkConfig";

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var ValuelinkConfig
	 */
	private $valuelinkConfig;

	/**
	 * @var SessionManagerInterface
	 */
	private $session;

	/**
	* @var \Magento\Store\Model\StoreManagerInterface $storeManager
	*/
	private $storeManager;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 * @param SessionManagerInterface $session
	 */
	public function __construct(
		Config $config,
		ValueLinkConfig $valuelinkConfig,
		SessionManagerInterface $session,
		StoreManagerInterface $storeManager
	) {
		$this->config = $config;
		$this->valuelinkConfig = $valuelinkConfig;
		$this->session = $session;
		$this->storeManager = $storeManager;
	}

	/**
	 * Retrieve assoc array of checkout configuration
	 *
	 * @return array
	 */
	public function getConfig()
	{
		$storeId = $this->session->getStoreId();
		$valuelinkData = [
			self::VALUELINK_TITLE_KEY => $this->valuelinkConfig->getValuelinkTitle($storeId),
			self::IS_ACTIVE_KEY => (bool) $this->valuelinkConfig->isActive($storeId)
		];

		if ($valuelinkData[self::IS_ACTIVE_KEY])
		{
			$valuelinkData[self::VALUELINK_FORM_CONFIG_KEY] = $this->buildValuelinkFormConfig(ValuelinkConfig::KEY_SDC_CHECKOUT, $storeId);
		}

		return [
			'payment' => [
				self::CODE => [
					self::IS_ACTIVE_KEY => $this->config->isActive($storeId),
					self::STORE_URL_KEY => $this->getBaseUrl(),
					self::STORE_ID_KEY => $this->storeManager->getStore()->getId(),
					self::WEBSITE_ID_KEY => $this->storeManager->getWebsite()->getId(),
					self::FISERV_VALUELINK_KEY => $valuelinkData
				]
			]
		];
	}

	private function getBaseUrl()
	{
		return $this->storeManager->getStore()->getBaseUrl();
	}

	public function buildValuelinkFormConfig($formId, $storeId)
	{
		$fieldsConfig = array();
		$fieldsConfig["fields"] = $this->buildValuelinkFormFieldsConfig($formId, $storeId);
		$fieldsConfig["css"] = json_decode($this->valuelinkConfig->cssFormConfig($formId, $storeId) ?? "{}");
		$fieldsConfig["font"] = $this->valuelinkConfig->fontFormConfig($formId, $storeId) ?? array();

		return $fieldsConfig;
	}	

	private function buildValuelinkFormFieldsConfig($formId, $storeId)
	{
		$cardNumberConfig = $this->valuelinkConfig->cardNumberFormConfig($formId, $storeId);
		$securityCodeConfig = $this->valuelinkConfig->securityCodeFormConfig($formId, $storeId);

		$fieldsConfig = array();

		$fieldsConfig["cardNumber"] = array(
			"parentElementId" => "fiserv_valuelink-card-number",
			"placeholder" => $cardNumberConfig[$this->valuelinkConfig::KEY_SDC_PLACEHOLDER],
			"dynamicPlaceholderCharacter" => $cardNumberConfig[$this->valuelinkConfig::KEY_SDC_PLACEHOLDER_CHAR],			
			"enableFormatting" => $cardNumberConfig[$this->valuelinkConfig::KEY_SDC_NUMBER_FORMAT] == 1 ? true : false,
			"masking" => array(
				"character" => $cardNumberConfig[$this->valuelinkConfig::KEY_SDC_MASK_CHAR],
				"mode" => $cardNumberConfig[$this->valuelinkConfig::KEY_SDC_MASK_MODE],
				"shrunkLength" => intval($cardNumberConfig[$this->valuelinkConfig::KEY_SDC_MASK_LENGTH])
			)
		);

		if ($cardNumberConfig[$this->valuelinkConfig::KEY_SDC_MASK] == 0)
		{
			$fieldsConfig["cardNumber"]["masking"]["mode"] = MaskingMode::NO_MASK;
		}

		$fieldsConfig["securityCode"] = array(
			"parentElementId" => "fiserv_valuelink-security-code",
			"placeholder" => $securityCodeConfig[$this->valuelinkConfig::KEY_SDC_PLACEHOLDER],
			"dynamicPlaceholderCharacter" => $securityCodeConfig[$this->valuelinkConfig::KEY_SDC_PLACEHOLDER_CHAR],			
			"masking" => array(
				"character" => $securityCodeConfig[$this->valuelinkConfig::KEY_SDC_MASK_CHAR],
				"mode" => $securityCodeConfig[$this->valuelinkConfig::KEY_SDC_MASK_MODE],
			)	
		);	

		if ($securityCodeConfig[$this->valuelinkConfig::KEY_SDC_MASK] == 0)
		{
			$fieldsConfig["securityCode"]["masking"]["mode"] = MaskingModeCvv::NO_MASK;
		}	

		return $fieldsConfig;
	}
}
