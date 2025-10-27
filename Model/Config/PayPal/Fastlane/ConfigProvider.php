<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Config\PayPal\Fastlane;

use Fiserv\Payments\Gateway\Config\PayPal\Config as PayPalConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ConfigProvider
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_paypal_fastlane';
	const IS_ACTIVE_KEY = 'isActive';

	private $paypalConfig;
	private $storeManager;

	public function __construct(
		PayPalConfig $paypalConfig,
		StoreManagerInterface $storeManager,
	){
		$this->paypalConfig = $paypalConfig;
		$this->storeManager = $storeManager;
	}

	public function getConfig()
	{
		$storeId = $this->storeManager->getStore()->getId();

		$config = [
			self::IS_ACTIVE_KEY => $this->paypalConfig->isFastlaneActive($storeId),
		];
		return [
			'payment' => [
				self::CODE => $config
			]
		];
	}
}
