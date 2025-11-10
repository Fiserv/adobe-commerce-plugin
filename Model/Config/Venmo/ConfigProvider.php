<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Config\Venmo;

use Fiserv\Payments\Gateway\Config\Venmo\Config as VenmoConfig;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\ResourceModel\Region\Collection;
use Magento\Checkout\Helper\Data as CheckoutHelper;

/**
 * Class ConfigProvider
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_venmo';
	const IS_ACTIVE_KEY = 'isActive';
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

	/** @var VenmoConfig */
	private $venmoConfig;
	/** @var CommerceHubConfig */
	private $commerceHubConfig;
	/** @var StoreManagerInterface */
	private $storeManager;

	private $regionCollection;

	private $checkoutHelper;

	public function __construct(
		VenmoConfig $venmoConfig,
		CommerceHubConfig $commerceHubConfig,
		StoreManagerInterface $storeManager,
		Collection $regionCollection,
		CheckoutHelper $checkoutHelper
	){
		$this->venmoConfig = $venmoConfig;
		$this->commerceHubConfig = $commerceHubConfig;
		$this->storeManager = $storeManager;
		$this->regionCollection = $regionCollection;
		$this->checkoutHelper = $checkoutHelper;
	}

	public function getConfig()
	{
		$storeId = $this->storeManager->getStore()->getId();
		$allowedFunding = ['venmo'];

		$buttonConfig = [
			'data' => [
				'buttons' => [
					'venmo' => [
						'shape' => $this->venmoConfig->getVenmoButtonShape($storeId) ?: 'rect',
						'parentElementId' => 'venmo-button-container'
					],
				]
			],
			'funding' => [
				'allowed' => $allowedFunding,
				'disallowed' => [],
			],
		];

		$config = [
			'buttonConfig' => $buttonConfig,
			self::IS_ACTIVE_KEY => $this->venmoConfig->isActive($storeId),
			self::MERCHANT_ID_KEY => $this->commerceHubConfig->getMerchantId($storeId),
			self::TERMINAL_ID_KEY => $this->commerceHubConfig->getTerminalId($storeId),
			self::API_KEY_KEY => $this->commerceHubConfig->getApiKey($storeId),
			self::ENV_KEY => method_exists($this->commerceHubConfig, 'getApiEnvironment') ? $this->commerceHubConfig->getApiEnvironment($storeId) : '',
			self::PAYMENT_ACTION_KEY => $this->venmoConfig->getPaymentAction($storeId),
			self::LOGGING_LEVEL_KEY => $this->commerceHubConfig->getLoggingLevel($storeId),
			self::CURRENCY_KEY => $this->commerceHubConfig->getCurrency($storeId),
			self::PROD_CLIENT_KEY => $this->commerceHubConfig->getProdClientUrl(),
			self::CERT_CLIENT_KEY => $this->commerceHubConfig->getCertClientUrl()
		];
		return [
			'payment' => [
				self::CODE => $config
			]
		];
	}

}
