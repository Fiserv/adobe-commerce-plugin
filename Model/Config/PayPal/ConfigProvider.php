<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Config\PayPal;

use Fiserv\Payments\Gateway\Config\PayPal\Config as PayPalConfig;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\ResourceModel\Region\Collection;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Framework\Locale\ResolverInterface as LocaleResolver;

/**
 * Class ConfigProvider
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_paypal';
	const IS_ACTIVE_KEY = 'isActive';
	const VAULT_CODE = 'fiserv_paypal_vault';
	const FASTLANE_CODE = 'fastlane';
	const MERCHANT_ID_KEY = 'merchantId';
	const TERMINAL_ID_KEY = 'terminalId';
	const API_KEY_KEY = 'apiKey';
	const ENV_KEY = 'environment';
	const PAYMENT_ACTION_KEY = 'payment_action';
	const LOGGING_LEVEL_KEY = 'loggingLevel';
	const CURRENCY_KEY = 'currency';
	const PROD_CLIENT_KEY = 'prodClientUrl';
	const CERT_CLIENT_KEY = 'certClientUrl';
	const IS_ONEPAGE_CHECKOUT_KEY = 'onepageCheckoutEnabled';
	const LOCALE_KEY = 'locale';

	/** @var PayPalConfig */
	private $paypalConfig;
	/** @var CommerceHubConfig */
	private $commerceHubConfig;
	/** @var StoreManagerInterface */
	private $storeManager;

	private $regionCollection;

	private $checkoutHelper;

	/** @var LocaleResolver */
	private $localeResolver;

	public function __construct(
		PayPalConfig $paypalConfig,
		CommerceHubConfig $commerceHubConfig,
		StoreManagerInterface $storeManager,
		Collection $regionCollection,
		CheckoutHelper $checkoutHelper,
		LocaleResolver $localeResolver
	){
		$this->paypalConfig = $paypalConfig;
		$this->commerceHubConfig = $commerceHubConfig;
		$this->storeManager = $storeManager;
		$this->regionCollection = $regionCollection;
		$this->checkoutHelper = $checkoutHelper;
		$this->localeResolver = $localeResolver;
	}

	/** Convert the locale into  IETF BCP47 format */
	private function getBcp47Locale(): string
	{
		$locale = (string) $this->localeResolver->getLocale();
		return $locale ? str_replace('_', '-', $locale) : 'en-US';
	}

	public function getConfig()
	{
		$storeId = $this->storeManager->getStore()->getId();
		$allowedFunding = ['paypal'];
		$vaultConfig = [
			'enableVault' => method_exists($this->paypalConfig, 'isVaultActive') ? $this->paypalConfig->isVaultActive($storeId) : false,
			'vaultLabel' => __('Save PayPal for future use'),
		];
		$buttonConfig = [
			'data' => [
				'vaulting' => $vaultConfig['enableVault'],
				'buttons' => [
					'paypal' => [
						'color' => $this->paypalConfig->getPaypalButtonColor($storeId) ?: 'gold',
						'shape' => $this->paypalConfig->getPaypalButtonShape($storeId) ?: 'rect',
						'label' => $this->paypalConfig->getPaypalButtonLabel($storeId) ?: 'paypal',
						'parentElementId' => 'paypal-button-container'
					],
				]
			],
			'funding' => [
				'allowed' => $allowedFunding,
				'disallowed' => [],
			],
		];

		$regionsByCountry = [];

		foreach ($this->regionCollection->getItems() as $region) {
			$countryId = $region->getCountryId();
			$regionCode = $region->getCode();

			$regionData = [
				'region_id'    => $region->getRegionId(),
				'region_code'  => $regionCode,
				'default_name' => $region->getDefaultName(),
				'name'         => $region->getName(),
			];

			if (!isset($regionsByCountry[$countryId])) {
				$regionsByCountry[$countryId] = [];
			}

			$regionsByCountry[$countryId][$regionCode] = $regionData;
		}

		$config = [
			'buttonConfig' => $buttonConfig,
			'vaultConfig' => $vaultConfig,
			self::FASTLANE_CODE => $this->paypalConfig->isFastlaneActive($storeId),
			self::IS_ONEPAGE_CHECKOUT_KEY => $this->checkoutHelper->canOnepageCheckout(),
			self::IS_ACTIVE_KEY => $this->paypalConfig->isActive($storeId),
			self::MERCHANT_ID_KEY => $this->commerceHubConfig->getMerchantId($storeId),
			self::TERMINAL_ID_KEY => $this->commerceHubConfig->getTerminalId($storeId),
			self::API_KEY_KEY => $this->commerceHubConfig->getApiKey($storeId),
			self::ENV_KEY => method_exists($this->commerceHubConfig, 'getApiEnvironment') ? $this->commerceHubConfig->getApiEnvironment($storeId) : '',
			self::PAYMENT_ACTION_KEY => $this->paypalConfig->getPaymentAction($storeId),
			self::LOGGING_LEVEL_KEY => $this->commerceHubConfig->getLoggingLevel($storeId),
			self::CURRENCY_KEY => $this->commerceHubConfig->getCurrency($storeId),
			self::PROD_CLIENT_KEY => $this->commerceHubConfig->getProdClientUrl(),
			self::CERT_CLIENT_KEY => $this->commerceHubConfig->getCertClientUrl(),
			self::LOCALE_KEY => $this->getBcp47Locale(),
			'regionsData' => $regionsByCountry
		];
		return [
			'payment' => [
				self::CODE => $config
			]
		];
	}
}
