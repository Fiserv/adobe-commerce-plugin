<?php
namespace Fiserv\Payments\Model\Config\Affirm;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Fiserv\Payments\Gateway\Config\Affirm\Config as AffirmConfig;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as ChConfigProvider;
/**
 * Affirm ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_affirm';
	const STORE_NAME_KEY = 'storeName';
	const BUTTON_COLOR_KEY = 'affirmButtonColor';

	private MultiLevelLogger $logger;

	/**
	 * @var AffirmConfig
	 */
	protected $config;

    /**
	 * @var SessionManagerInterface
	 */
	private $session;

	private $storeManager;

	private $chConfig;

	/**
	 * Constructor
	 *
	 * @param AffirmConfig $config
	 * @param SessionManagerInterface $session
	 * @param MultiLevelLogger $logger
	 */
	public function __construct(
		AffirmConfig $config,
		SessionManagerInterface $session,
		StoreManagerInterface $storeManager,
		ChConfig $chConfig,
		MultiLevelLogger $logger
	) {
		$this->config = $config;
		$this->session = $session;
		$this->storeManager = $storeManager;
		$this->chConfig = $chConfig;
		$this->logger = $logger;
	}

	/**
	 * Retrieve Apple Pay checkout configuration
	 *
	 * @return array
	 */
	public function getConfig()
	{
		$storeId = $this->session->getStoreId();

		$config = [
			ChConfigProvider::IS_ACTIVE_KEY => $this->config->isActive($storeId),
			ChConfigProvider::PAYMENT_ACTION_KEY => $this->config->getPaymentAction($storeId),
			self::STORE_NAME_KEY => $this->storeManager->getStore()->getName(),
			ChConfigProvider::MERCHANT_ID_KEY => $this->chConfig->getMerchantId($storeId),
			ChConfigProvider::TERMINAL_ID_KEY => $this->chConfig->getTerminalId($storeId),
			ChConfigProvider::ENV_KEY => $this->chConfig->getApiEnvironment($storeId),
			ChConfigProvider::API_KEY_KEY => $this->chConfig->getApiKey($storeId),
			self::BUTTON_COLOR_KEY => $this->config->getAffirmButtonStyle($storeId)
		];
		return ['payment' => [self::CODE => $config]];
	}
}
