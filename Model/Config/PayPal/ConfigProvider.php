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
	const SHOW_PRIVACY_STATEMENT_KEY = 'show_privacy_statement';
	const API_KEY_KEY = 'apiKey';
	const ENV_KEY = 'environment';
	const PAYMENT_ACTION_KEY = 'payment_action';
	const LOGGING_LEVEL_KEY = 'loggingLevel';
	const CURRENCY_KEY = 'currency';
	const PROD_CLIENT_KEY = 'prodClientUrl';
	const CERT_CLIENT_KEY = 'certClientUrl';

	/** @var PayPalConfig */
	private $paypalConfig;
	/** @var CommerceHubConfig */
	private $commerceHubConfig;
	/** @var StoreManagerInterface */
	private $storeManager;

	public function __construct(PayPalConfig $paypalConfig, CommerceHubConfig $commerceHubConfig, StoreManagerInterface $storeManager)
	{
		$this->paypalConfig = $paypalConfig;
		$this->commerceHubConfig = $commerceHubConfig;
		$this->storeManager = $storeManager;
	}

	public function getConfig()
	{
		$storeId = $this->storeManager->getStore()->getId();
		$allowedFunding = ['paypal'];
		if (method_exists($this->paypalConfig, 'isVenmoActive') && $this->paypalConfig->isVenmoActive($storeId)) {
			$allowedFunding[] = 'venmo';
		}
		$buttonConfig = [
			'style' => [
				'layout' => 'vertical',
				'color' => $this->paypalConfig->getPaypalButtonColor($storeId) ?: 'gold',
				'shape' => $this->paypalConfig->getPaypalButtonShape($storeId) ?: 'rect',
				'label' => $this->paypalConfig->getPaypalButtonLabel($storeId) ?: 'paypal',
			],
			// 'venmo_style' => [
			// 	'color' => $this->paypalConfig->getVenmoButtonColor($storeId) ?: 'gold',
			// 	'shape' => $this->paypalConfig->getVenmoButtonShape($storeId) ?: 'rect',
			// ],
			'funding' => [
				'allowed' => $allowedFunding,
				'disallowed' => [],
			],
		];
		$vaultConfig = [
			'enableVault' => method_exists($this->paypalConfig, 'isVaultActive') ? $this->paypalConfig->isVaultActive($storeId) : false,
			'vaultLabel' => __('Save PayPal for future use'),
		];
		// $venmoConfig = [
		// 	'enableVenmo' => method_exists($this->paypalConfig, 'isVenmoActive') ? $this->paypalConfig->isVenmoActive($storeId) : false,
		// 	'venmoLabel' => __('Pay with Venmo'),
		// ];
		$config = [
			'buttonConfig' => $buttonConfig,
			'vaultConfig' => $vaultConfig,
			// 'venmoConfig' => $venmoConfig,
			self::FASTLANE_CODE => $this->paypalConfig->isFastlaneActive($storeId),
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
		];
		return [
			'payment' => [
				self::CODE => $config
			]
		];
	}
	
}
