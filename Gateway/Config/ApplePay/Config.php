<?php
namespace Fiserv\Payments\Gateway\Config\ApplePay;

class Config extends \Magento\Payment\Gateway\Config\Config
{
	const CODE = 'fiserv_applepay';

	const KEY_ACTIVE = 'payment_active';
	const KEY_TITLE = 'title';
	const KEY_PAYMENT_ACTION = 'payment_action';
	const APPLEPAY_BUTTON_STYLE_KEY = 'applepay_button_style';
	const APPLEPAY_BUTTON_TYPE_KEY = 'applepay_button_type';

	// Recurring payment config keys
	const KEY_RECURRING_ENABLED              = 'recurring_enabled';
	const KEY_RECURRING_ORDER_DESCRIPTION    = 'recurring_order_description';
	const KEY_RECURRING_TOKEN_PROVIDER       = 'recurring_token_provider';
	const KEY_RECURRING_TOKEN_NOTIFICATION_URL = 'recurring_token_notification_url';
	const KEY_RECURRING_SUBSCRIPTION_TYPE   = 'recurring_subscription_type';
	const KEY_RECURRING_BILLING_AGREEMENT   = 'recurring_billing_agreement';
	const KEY_RECURRING_MANAGEMENT_URL      = 'recurring_management_url';
	const KEY_RECURRING_FREE_CANCELLATION_DATE = 'recurring_free_cancellation_date';
	const KEY_RECURRING_FREE_CANCELLATION_TIMEZONE = 'recurring_free_cancellation_timezone';
	const KEY_RECURRING_PAYMENT_ITEMS       = 'recurring_payment_items';


	/**
	 * Constructor
	 *
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	 * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
	 */
	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Framework\Serialize\Serializer\Json $serializer = null
	) {
		parent::__construct($scopeConfig, self::CODE, self::DEFAULT_PATH_PATTERN);
	}

	/**
	 * Is Apple Pay active
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isActive($storeId = null): bool
	{
		return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
	}

	/**
	 * Get Apple Pay title
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getTitle($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_TITLE, $storeId);
	}

	/**
	 * Get Apple Pay payment action
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getPaymentAction($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}

	public function getApplePayButtonStyle($storeId = null)
	{
		return $this->getValue(self::APPLEPAY_BUTTON_STYLE_KEY, $storeId);
	}

	public function getApplePayButtonType($storeId = null)
	{
		return $this->getValue(self::APPLEPAY_BUTTON_TYPE_KEY, $storeId);
	}

	// ── Recurring payment getters ──────────────────────────────────────────

	public function isRecurringEnabled($storeId = null): bool
	{
		return (bool) $this->getValue(self::KEY_RECURRING_ENABLED, $storeId);
	}

	public function getRecurringOrderDescription($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_RECURRING_ORDER_DESCRIPTION, $storeId);
	}

	public function getRecurringTokenProvider($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_RECURRING_TOKEN_PROVIDER, $storeId);
	}

	public function getRecurringTokenNotificationUrl($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_RECURRING_TOKEN_NOTIFICATION_URL, $storeId);
	}

	public function getRecurringSubscriptionType($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_RECURRING_SUBSCRIPTION_TYPE, $storeId);
	}

	public function getRecurringBillingAgreement($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_RECURRING_BILLING_AGREEMENT, $storeId);
	}

	public function getRecurringManagementUrl($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_RECURRING_MANAGEMENT_URL, $storeId);
	}

	public function getRecurringFreeCancellationDate($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_RECURRING_FREE_CANCELLATION_DATE, $storeId);
	}

	public function getRecurringFreeCancellationTimezone($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_RECURRING_FREE_CANCELLATION_TIMEZONE, $storeId);
	}

	/**
	 * Returns the payment items as a decoded array, or an empty array on failure.
	 */
	public function getRecurringPaymentItems($storeId = null): array
	{
		$raw = (string) $this->getValue(self::KEY_RECURRING_PAYMENT_ITEMS, $storeId);
		if (empty($raw)) {
			return [];
		}
		$decoded = json_decode($raw, true);
		return is_array($decoded) ? $decoded : [];
	}
}

