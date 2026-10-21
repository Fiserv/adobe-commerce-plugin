<?php
namespace Fiserv\Payments\Model\Config\Klarna;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Fiserv\Payments\Gateway\Config\Klarna\Config as KlarnaConfig;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as ChConfigProvider;

/**
 * Klarna ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_klarna';
	const STORE_NAME_KEY = 'storeName';
	const BUTTON_COLOR_KEY = 'klarnaButtonColor';
	const BUTTON_SHAPE_KEY = 'klarnaButtonShape';
	const LOGO_ALIGNMENT_KEY = 'klarnaLogoAlignment';

	private MultiLevelLogger $logger;

	/**
	 * @var KlarnaConfig
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
	 * @param KlarnaConfig $config
	 * @param SessionManagerInterface $session
	 * @param StoreManagerInterface $storeManager
	 * @param ChConfig $chConfig
	 * @param MultiLevelLogger $logger
	 */
	public function __construct(
		KlarnaConfig $config,
		SessionManagerInterface $session,
		StoreManagerInterface $storeManager,
		ChConfig $chConfig,
		MultiLevelLogger $logger
	)
	{
		$this->config = $config;
		$this->session = $session;
		$this->storeManager = $storeManager;
		$this->chConfig = $chConfig;
		$this->logger = $logger;
	}

	/**
	 * Retrieve Klarna checkout configuration
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
			self::BUTTON_COLOR_KEY => $this->config->getKlarnaButtonColor($storeId),
			self::BUTTON_SHAPE_KEY => $this->config->getKlarnaButtonShape($storeId),
			self::LOGO_ALIGNMENT_KEY => $this->config->getKlarnaLogoAlignment($storeId)
		];

		return ['payment' => [self::CODE => $config]];
	}
}

