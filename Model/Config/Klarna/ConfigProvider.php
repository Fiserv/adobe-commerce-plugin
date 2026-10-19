<?php
namespace Fiserv\Payments\Model\Config\Klarna;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Fiserv\Payments\Gateway\Config\Klarna\Config as KlarnaConfig;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as ChConfigProvider;
use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Klarna ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_klarna';
	const STORE_NAME_KEY = 'storeName';
	const THEME_KEY = 'theme';
	const SHAPE_KEY = 'shape';
	const LOGO_ALIGNMENT_KEY = 'logoAlignment';

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
	 * @var CheckoutSession
	 */
	private $checkoutSession;

	/**
	 * Constructor
	 *
	 * @param KlarnaConfig $config
	 * @param SessionManagerInterface $session
	 * @param StoreManagerInterface $storeManager
	 * @param ChConfig $chConfig
	 * @param MultiLevelLogger $logger
	 * @param CheckoutSession $checkoutSession
	 */
	public function __construct(
		KlarnaConfig $config,
		SessionManagerInterface $session,
		StoreManagerInterface $storeManager,
		ChConfig $chConfig,
		MultiLevelLogger $logger,
		CheckoutSession $checkoutSession
	)
	{
		$this->config = $config;
		$this->session = $session;
		$this->storeManager = $storeManager;
		$this->chConfig = $chConfig;
		$this->logger = $logger;
		$this->checkoutSession = $checkoutSession;
	}

	/**
	 * Retrieve Klarna checkout configuration
	 *
	 * @return array
	 */
	public function getConfig()
	{
		$storeId = $this->session->getStoreId();
		$store = $this->storeManager->getStore($storeId);
		$storeId = $store->getId();
		$quote = $this->checkoutSession->getQuote();

		// Klarna requires the actual quote currency to be USD, not the configured base currency.
		$isActive = $this->config->isActive($storeId)
			&& $this->chConfig->isActive($storeId)
			&& in_array($this->chConfig->getApiEnvironment($storeId), ['CERT', 'PROD'], true)
			&& $quote->getQuoteCurrencyCode() === 'USD'
			&& $quote->getBaseCurrencyCode() === 'USD';

		$config = [
			ChConfigProvider::IS_ACTIVE_KEY => $isActive,
			ChConfigProvider::PAYMENT_ACTION_KEY => $this->config->getPaymentAction($storeId),
			self::STORE_NAME_KEY => $store->getName(),
			ChConfigProvider::ENV_KEY => $this->chConfig->getApiEnvironment($storeId),
			self::THEME_KEY => $this->config->getTheme($storeId),
			self::SHAPE_KEY => $this->config->getShape($storeId),
			self::LOGO_ALIGNMENT_KEY => $this->config->getLogoAlignment($storeId),
		];

		return ['payment' => [self::CODE => $config]];
	}
}
