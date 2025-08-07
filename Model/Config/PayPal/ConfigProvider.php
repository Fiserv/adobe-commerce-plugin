<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Config\PayPal;

use Fiserv\Payments\Gateway\Config\PayPal\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class ConfigProvider
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'ch_paypal';
	const IS_FASTLANE_ACTIVE_KEY = 'isFastlaneActive';
	const MERCHANT_ID_KEY = 'merchantId';
	const API_KEY_KEY = 'apiKey';
	const LOGGING_LEVEL_KEY = 'loggingLevel';
	const ENV_KEY = 'environment';
	const CURRENCY_KEY = 'currency';
	const PROD_CLIENT_KEY = 'prodClientUrl';
	const CERT_CLIENT_KEY = 'certClientUrl';
	const TERMINAL_ID_KEY = 'terminalId';

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var StoreManagerInterface
	 */
	private $storeManager;
	private $logger;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 * @param StoreManagerInterface $storeManager
	 */
	public function __construct(
		Config $config,
		StoreManagerInterface $storeManager,
		MultiLevelLogger $logger
	) {
		$this->config = $config;
		$this->storeManager = $storeManager;
		$this->logger = $logger;
	}

	/**
	 * Retrieve assoc array of checkout configuration
	 *
	 * @return array
	 */
	public function getConfig()
	{
		$storeId = $this->storeManager->getStore()->getId();

		$config = [
			self::IS_FASTLANE_ACTIVE_KEY => $this->config->isFastlaneActive($storeId),
			self::MERCHANT_ID_KEY => $this->config->getMerchantId($storeId),
			self::API_KEY_KEY => $this->config->getApiKey(),
			self::LOGGING_LEVEL_KEY => $this->config->getLoggingLevel($storeId),
			self::ENV_KEY => $this->config->getApiEnvironment($storeId),
			self::CURRENCY_KEY => $this->config->getCurrency($storeId),
			self::PROD_CLIENT_KEY => $this->config->getProdClientUrl(),
			self::CERT_CLIENT_KEY => $this->config->getCertClientUrl(),
			self::TERMINAL_ID_KEY => $this->config->getTerminalId($storeId),
		];

		return [
			'payment' => [
				self::CODE => $config
			]
		];
	}
}

