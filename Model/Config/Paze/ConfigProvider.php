<?php
namespace Fiserv\Payments\Model\Config\Paze;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Fiserv\Payments\Gateway\Config\Paze\Config as PazeConfig;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as ChConfigProvider;
/**
 * Paze ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_paze';
	const STORE_NAME_KEY = 'storeName';

	private MultiLevelLogger $logger;

	/**
	 * @var PazeConfig
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
	 * @param PazeConfig $config
	 * @param SessionManagerInterface $session
     * @param MultiLevelLogger $logger
	 */
	public function __construct(
		PazeConfig $config,
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
	 * Retrieve Paze checkout configuration
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
			ChConfigProvider::API_KEY_KEY => $this->chConfig->getApiKey($storeId)
		];
		return ['payment' => [self::CODE => $config]];
	}
}
