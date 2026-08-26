<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Config\PayByLink;

use Fiserv\Payments\Gateway\Config\PayByLink\Config as Config;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\ResourceModel\Region\Collection;
use Magento\Checkout\Helper\Data as CheckoutHelper;

class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_pay_by_link';
	const IS_ACTIVE_KEY = 'isActive';
    const EXPIRY_TIME_KEY = 'expiryTime';
    const PAYMENT_PAGE_ID_KEY = 'paymentPageId';
	const OPTIONAL_NOTE_KEY = 'optionalNote';

	/**
	 * @var Config
	 */
	private $config;

    /**
     * @var CommerceHubConfig
     */
    private $commerceHubConfig;

	/**
	 * @var SessionManagerInterface
	 */
	private $session;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 * @param CommerceHubConfig $commerceHubConfig
	 * @param SessionManagerInterface $session
	 */
	public function __construct(
		Config $config,
		CommerceHubConfig $commerceHubConfig,
		SessionManagerInterface $session
	) {
		$this->config = $config;
		$this->commerceHubConfig = $commerceHubConfig;
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
			self::IS_PAYMENT_ACTIVE_KEY => $this->config->isPaymentActive($storeId),
			self::MERCHANT_ID_KEY => $this->commerceHubConfig->getMerchantId($storeId),
			self::API_KEY_KEY => $this->commerceHubConfig->getApiKey(),
			self::ENV_KEY => $this->commerceHubConfig->getApiEnvironment($storeId),
			self::LOGGING_LEVEL_KEY => $this->commerceHubConfig->getLoggingLevel($storeId),
			self::CURRENCY_KEY => $this->config->getCurrency($storeId),
			self::PROD_CLIENT_KEY => $this->commerceHubConfig->getProdClientUrl(),
			self::CERT_CLIENT_KEY => $this->commerceHubConfig->getCertClientUrl(),
			self::TERMINAL_ID_KEY => $this->commerceHubConfig->getTerminalId($storeId),
            self::EXPIRY_TIME_KEY => $this->config->getExpiryTime($storeId),
            self::PAYMENT_PAGE_ID_KEY => $this->config->getPaymentPageId($storeId),
            self::OPTIONAL_NOTE_KEY => $this->config->isOptionalNoteEnabled($storeId)
		];

		return [
			'payment' => [
				self::CODE => $config
			]
		];
	}
}
