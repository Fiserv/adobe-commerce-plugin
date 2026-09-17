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
use Magento\Framework\Locale\ResolverInterface as LocaleResolverInterface;
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
	const LOCALE_KEY = "locale";
	const DEFAULT_LOCALE = 'en-US';
	const SUPPORTED_LOCALES = [
		'da-dk' => 'da-DK',
		'de-de' => 'de-DE',
		'en-gb' => 'en-GB',
		'en-us' => self::DEFAULT_LOCALE,
		'es-es' => 'es-ES',
		'fa-ir' => 'fa-IR',
		'fr-fr' => 'fr-FR',
		'fy-nl' => 'fy-NL',
		'hi-in' => 'hi-IN',
		'it-it' => 'it-IT',
		'iw-il' => 'iw-IL',
		'ja-jp' => 'ja-JP',
		'ko-kr' => 'ko-KR',
		'ms-my' => 'ms-MY',
		'nl-nl' => 'nl-NL',
		'no-no' => 'no-NO',
		'pl-pl' => 'pl-PL',
		'pt-pt' => 'pt-PT',
		'sv-se' => 'sv-SE',
		'uk-ua' => 'uk-UA',
		'vi-vn' => 'vi-VN',
		'zh-cn' => 'zh-CN',
		'zh-tw' => 'zh-TW'
	];
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
	 * @var LocaleResolverInterface
	 */
	private $localeResolver;

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
		StoreManagerInterface $storeManager,
		LocaleResolverInterface $localeResolver
	) {
		$this->config = $config;
		$this->valuelinkConfig = $valuelinkConfig;
		$this->session = $session;
		$this->storeManager = $storeManager;
		$this->localeResolver = $localeResolver;
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
					self::LOCALE_KEY => $this->getBcp47Locale(),
					self::FISERV_VALUELINK_KEY => $valuelinkData
				]
			]
		];
	}

	private function getBaseUrl()
	{
		return $this->storeManager->getStore()->getBaseUrl();
	}

	/**
	 * Resolve a supported active store locale.
	 * Empty and unsupported locales fall back to en-US.
	 */
	private function getBcp47Locale()
	{
		$locale = str_replace('_', '-', trim((string) $this->localeResolver->getLocale()));

		return self::SUPPORTED_LOCALES[strtolower($locale)] ?? self::DEFAULT_LOCALE;
	}

	public function buildValuelinkFormConfig($formId, $storeId)
	{
		$fieldsConfig = array();
		$fieldsConfig["fields"] = $this->buildValuelinkFormFieldsConfig($formId, $storeId);
		$fieldsConfig["css"] = json_decode($this->valuelinkConfig->cssFormConfig($formId, $storeId) ?? "{}");

		$fontConfig = $this->valuelinkConfig->fontFormConfig($formId, $storeId) ?? array();
		if ($this->hasRequiredFontConfig($fontConfig)) {
			$fieldsConfig["font"] = $fontConfig;
		}

		return $fieldsConfig;
	}	

	private function hasRequiredFontConfig($fontConfig)
	{
		return
			!empty(trim((string)($fontConfig[ValuelinkConfig::KEY_FONT_DATA] ?? ''))) &&
			!empty(trim((string)($fontConfig[ValuelinkConfig::KEY_FONT_FAMILY] ?? ''))) &&
			!empty(trim((string)($fontConfig[ValuelinkConfig::KEY_FONT_FORMAT] ?? '')));
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
