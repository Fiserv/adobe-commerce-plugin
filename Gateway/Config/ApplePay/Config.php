<?php
namespace Fiserv\Payments\Gateway\Config\ApplePay;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;

/**
 * Apple Pay Config class extending CommerceHub config
 */
class Config extends CommerceHubConfig
{
	const CODE = 'fiserv_commercehub';

	const KEY_ACTIVE = 'active';
	const KEY_TITLE = 'applepay_title';
	const KEY_PAYMENT_ACTION = 'applepay_payment_action';

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

	/**
	 * Get API environment (delegated to CommerceHub)
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getEnvironment($storeId = null): string
	{
		return $this->getApiEnvironment($storeId);
	}
}
