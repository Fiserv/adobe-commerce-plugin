<?php
namespace Fiserv\Payments\Gateway\Config\ApplePay;

class Config extends \Magento\Payment\Gateway\Config\Config
{
	const CODE = 'fiserv_applepay';

	const KEY_ACTIVE = 'active';
	const KEY_TITLE = 'title';
	const KEY_PAYMENT_ACTION = 'payment_action';
	const APPLEPAY_BUTTON_STYLE_KEY = 'applepay_button_style';
	const APPLEPAY_BUTTON_TYPE_KEY = 'applepay_button_type';


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

}

