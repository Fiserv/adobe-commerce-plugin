<?php
namespace Fiserv\Payments\Model\Config\Klarna;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Fiserv\Payments\Gateway\Config\Klarna\Config as KlarnaConfig;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as ChConfigProvider;
use Fiserv\Payments\Model\Klarna\TokenManagement;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Klarna ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_klarna';
	const VAULT_CODE = 'fiserv_klarna_vault';
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

	private $customerSession;

	private $tokenManagement;

	/**
	 * Constructor
	 *
	 * @param KlarnaConfig $config
	 * @param SessionManagerInterface $session
	 * @param StoreManagerInterface $storeManager
	 * @param ChConfig $chConfig
	 * @param MultiLevelLogger $logger
	 * @param CustomerSession $customerSession
	 * @param TokenManagement $tokenManagement
	 */
	public function __construct(
		KlarnaConfig $config,
		SessionManagerInterface $session,
		StoreManagerInterface $storeManager,
		ChConfig $chConfig,
		MultiLevelLogger $logger,
		CustomerSession $customerSession,
		TokenManagement $tokenManagement
	)
	{
		$this->config = $config;
		$this->session = $session;
		$this->storeManager = $storeManager;
		$this->chConfig = $chConfig;
		$this->logger = $logger;
		$this->customerSession = $customerSession;
		$this->tokenManagement = $tokenManagement;
	}

	/**
	 * Retrieve Klarna checkout configuration
	 *
	 * @return array
	 */
	public function getConfig()
	{
		$storeId = $this->session->getStoreId();
		$customerId = $this->customerSession->isLoggedIn() ? $this->customerSession->getCustomerId() : 0;
		$original = $this->tokenManagement->getOriginal($customerId);
		$saved = [];
		$notice = '';
		if ($original && $this->config->isActive($storeId)) {
			// Card-oriented visible-token queries exclude NULL expiry; Klarna has no gateway expiry.
			$token = $this->tokenManagement->getAvailable($customerId, $original['public_hash'], $this->storeManager->getStore()->getId());
			$expired = $this->tokenManagement->isExpired($original['expires_at']);
			$notice = __('Your saved Klarna method is unavailable. Use one-time checkout; your original saved method will not be replaced.')->render();
			if ($expired) {
				$notice = __('Your saved Klarna method has expired. Use one-time checkout; your original saved method will not be replaced.')->render();
			}
			if ($token !== null) {
				$saved[] = ['publicHash' => $token->getPublicHash(), 'label' => __('Klarna')->render()];
				$notice = '';
			}
		}

		$config = [
			'canSave' => $customerId > 0 && $original === null,
			'savedTokens' => $saved,
			'savedTokenNotice' => $notice,
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
