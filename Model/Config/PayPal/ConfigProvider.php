<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Config\PayPal;

use Fiserv\Payments\Gateway\Config\PayPal\Config as PayPalConfig;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as PaymentConfig;
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
	const CODE = 'fiserv_paypal_fastlane';
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
	 * @var PayPalConfig
	 */
	private $payPalConfig;

	/**
	 * @var PaymentConfig
	 */
	private $paymentConfig;

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
		PayPalConfig $payPalConfig,
		PaymentConfig $paymentConfig,
		StoreManagerInterface $storeManager,
		MultiLevelLogger $logger
	) {
		$this->payPalConfig = $payPalConfig;
		$this->paymentConfig = $paymentConfig;
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
			self::IS_FASTLANE_ACTIVE_KEY => $this->payPalConfig->isFastlaneActive($storeId),
			self::MERCHANT_ID_KEY => $this->paymentConfig->getMerchantId($storeId),
			self::API_KEY_KEY => $this->paymentConfig->getApiKey(),
			self::LOGGING_LEVEL_KEY => $this->paymentConfig->getLoggingLevel($storeId),
			self::ENV_KEY => $this->paymentConfig->getApiEnvironment($storeId),
			self::CURRENCY_KEY => $this->paymentConfig->getCurrency($storeId),
			self::PROD_CLIENT_KEY => $this->paymentConfig->getProdClientUrl(),
			self::CERT_CLIENT_KEY => $this->paymentConfig->getCertClientUrl(),
			self::TERMINAL_ID_KEY => $this->paymentConfig->getTerminalId($storeId),
		];

		return [
			'payment' => [
				self::CODE => $config
			]
		];
	}
}

