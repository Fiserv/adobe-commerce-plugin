<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Config;

use Fiserv\Payments\Gateway\Config\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Session\SessionManagerInterface;
use \Magento\Store\Model\StoreManagerInterface;

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

	/**
	 * @var Config
	 */
	private $config;

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
		SessionManagerInterface $session,
		StoreManagerInterface $storeManager
	) {
		$this->config = $config;
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
		return [
			'payment' => [
				self::CODE => [
					self::IS_ACTIVE_KEY => $this->config->isActive($storeId),
					self::STORE_URL_KEY => $this->getBaseUrl(),
					self::STORE_ID_KEY => $this->storeManager->getStore()->getId(),
					self::WEBSITE_ID_KEY => $this->storeManager->getWebsite()->getId()
				]
			]
		];
	}

	private function getBaseUrl()
	{
		return $this->storeManager->getStore()->getBaseUrl();
	}
}
