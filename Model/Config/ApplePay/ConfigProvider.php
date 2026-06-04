<?php
namespace Fiserv\Payments\Model\Config\ApplePay;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Fiserv\Payments\Gateway\Config\ApplePay\Config as ApplePayConfig;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as ChConfigProvider;
/**
 * Apple Pay ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_applepay';
	const STORE_NAME_KEY = 'storeName';
	const BUTTON_STYLE_KEY = 'applepayButtonStyle';
	const BUTTON_TYPE_KEY = 'applepayButtonType';

	// Recurring keys exposed to JS
	const RECURRING_ENABLED_KEY                  = 'recurringEnabled';
	const RECURRING_ORDER_DESCRIPTION_KEY        = 'recurringOrderDescription';
	const RECURRING_TOKEN_PROVIDER_KEY           = 'recurringTokenProvider';
	const RECURRING_TOKEN_NOTIFICATION_URL_KEY   = 'recurringTokenNotificationUrl';
	const RECURRING_SUBSCRIPTION_TYPE_KEY        = 'recurringSubscriptionType';
	const RECURRING_BILLING_AGREEMENT_KEY        = 'recurringBillingAgreement';
	const RECURRING_MANAGEMENT_URL_KEY           = 'recurringManagementUrl';
	const RECURRING_FREE_CANCELLATION_DATE_KEY   = 'recurringFreeCancellationDate';
	const RECURRING_FREE_CANCELLATION_TZ_KEY     = 'recurringFreeCancellationTimezone';
	const RECURRING_PAYMENT_ITEMS_KEY            = 'recurringPaymentItems';

	private MultiLevelLogger $logger;

	/**
	 * @var ApplePayConfig
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
	 * @param ApplePayConfig $config
	 * @param SessionManagerInterface $session
     * @param MultiLevelLogger $logger
	 */
	public function __construct(
		ApplePayConfig $config,
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
			self::BUTTON_STYLE_KEY => $this->config->getApplePayButtonStyle($storeId),
			self::BUTTON_TYPE_KEY => $this->config->getApplePayButtonType($storeId),

			// Recurring payment configuration
            self::RECURRING_ENABLED_KEY => $this->config->isRecurringEnabled($storeId),
            self::RECURRING_ORDER_DESCRIPTION_KEY => $this->config->getRecurringOrderDescription($storeId),
            self::RECURRING_TOKEN_PROVIDER_KEY => $this->config->getRecurringTokenProvider($storeId),
            self::RECURRING_TOKEN_NOTIFICATION_URL_KEY => $this->config->getRecurringTokenNotificationUrl($storeId),
            self::RECURRING_SUBSCRIPTION_TYPE_KEY => $this->config->getRecurringSubscriptionType($storeId),
            self::RECURRING_BILLING_AGREEMENT_KEY => $this->config->getRecurringBillingAgreement($storeId),
            self::RECURRING_MANAGEMENT_URL_KEY => $this->config->getRecurringManagementUrl($storeId),
            self::RECURRING_FREE_CANCELLATION_DATE_KEY => $this->config->getRecurringFreeCancellationDate($storeId),
            self::RECURRING_FREE_CANCELLATION_TZ_KEY => $this->config->getRecurringFreeCancellationTimezone($storeId),
            self::RECURRING_PAYMENT_ITEMS_KEY => $this->config->getRecurringPaymentItems($storeId),
		];
		return ['payment' => [self::CODE => $config]];
	}
}
