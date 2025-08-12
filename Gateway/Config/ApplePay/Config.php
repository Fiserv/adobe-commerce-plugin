<?php

namespace Fiserv\Payments\Gateway\Config\ApplePay;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	// Constants for Apple Pay configuration keys
	const KEY_ACTIVE = 'applepay_active';
	const KEY_PAYMENT_ACTION = 'applepay_payment_action';
	const KEY_TITLE = 'applepay_title';
	const KEY_PRIVACY_STATEMENT = 'show_privacy_statement';

	/**
	 * @var \Magento\Framework\Serialize\Serializer\Json
	 */
	private $serializer;

	/**
	 * ApplePay Config constructor
	 *
	 * @param ScopeConfigInterface $scopeConfig
	 * @param null|string $methodCode
	 * @param string $pathPattern
	 * @param Json|null $serializer
	 */
	public function __construct(
		ScopeConfigInterface $scopeConfig,
							 $methodCode = null,
							 $pathPattern = self::DEFAULT_PATH_PATTERN,
		Json $serializer = null
	) {
		parent::__construct($scopeConfig, $methodCode, $pathPattern);
		$this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()->get(Json::class);
	}

	/**
	 * Gets Apple Pay configuration status.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isApplePayActive($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
	}

	/**
	 * Gets value of Apple Pay payment action.
	 *
	 * Possible values: Sale or Authorize.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getApplePayPaymentAction($storeId = null)
	{
		return $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}

	/**
	 * Retrieve title for Apple Pay section
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function getApplePayTitle($storeId = null)
	{
		return $this->getValue(self::KEY_TITLE, $storeId);
	}

	/**
	 * Gets privacy statement configuration status for Apple Pay.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function showApplePayPrivacyStatement($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_PRIVACY_STATEMENT, $storeId);
	}
}
