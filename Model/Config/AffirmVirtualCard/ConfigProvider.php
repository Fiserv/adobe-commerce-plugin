<?php
namespace Fiserv\Payments\Model\Config\AffirmVirtualCard;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Fiserv\Payments\Gateway\Config\AffirmVirtualCard\Config as AffirmVirtualCardConfig;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as ChConfigProvider;

/**
 * Affirm Virtual Card ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_affirm_virutal_card';
	const STORE_NAME_KEY = 'storeName';
	const TITLE_KEY = 'title';
	const SDK_URL_KEY = 'sdkUrl';

	private MultiLevelLogger $logger;

	/**
	 * @var AffirmVirtualCardConfig
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
	 * @param AffirmVirtualCardConfig $config
	 * @param SessionManagerInterface $session
	 * @param StoreManagerInterface $storeManager
	 * @param ChConfig $chConfig
	 * @param MultiLevelLogger $logger
	 */
	public function __construct(
		AffirmVirtualCardConfig $config,
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
	 * Retrieve Affirm Virtual Card checkout configuration
	 *
	 * @return array
	 */
	public function getConfig()
	{
		$storeId = $this->storeManager->getStore()->getId();
		$environment = $this->getEnvironment($storeId);
		// Match the checkout SDK version used by view/base/requirejs-config.js.
		$sdkHost = $environment === 'PROD'
			? 'https://commercehub-checkout.fiservapps.com'
			: 'https://commercehub-checkout-cert.fiservapps.com';

		$config = [
			ChConfigProvider::IS_ACTIVE_KEY => $this->config->isActive($storeId),
			ChConfigProvider::PAYMENT_ACTION_KEY => $this->config->getPaymentAction($storeId),
			self::STORE_NAME_KEY => $this->storeManager->getStore()->getName(),
			// Session credentials remain server-side; the SDK receives only sdkData.
			self::TITLE_KEY => $this->config->getTitle($storeId),
			self::SDK_URL_KEY => $sdkHost . '/sdk/4.3.18/checkout.js',
		];

		return ['payment' => [self::CODE => $config]];
	}

	/**
	 * Retrieve the shared checkout environment.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getEnvironment($storeId = null): string
	{
		if ($storeId === null)
		{
			$storeId = $this->storeManager->getStore()->getId();
		}

		return (string) $this->chConfig->getApiEnvironment($storeId);
	}
}
