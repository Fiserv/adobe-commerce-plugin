<?php

namespace Fiserv\Payments\Gateway\Config\PayPalFastLane;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	// Constants for PayPal Fastlane configuration keys
	const KEY_ACTIVE = 'active';
	const KEY_PAYMENT_ACTION = 'payment_action';
	const KEY_TITLE = 'title';
	const KEY_PRIVACY_STATEMENT = 'show_privacy_statement';

	/**
	 * @var \Magento\Framework\Serialize\Serializer\Json
	 */
	private $serializer;

	/**
	 * PayPal Fastlane Config constructor
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
	 * Gets PayPal Fastlane configuration status.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isActive($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
	}

	/**
	 * Gets value of PayPal Fastlane payment action.
	 *
	 * Possible values: Sale or Authorize.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getPaymentAction($storeId = null)
	{
		return $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}

	/**
	 * Retrieve title for PayPal Fastlane section.
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function getPaymentMethodTitle($storeId = null)
	{
		return $this->getValue(self::KEY_TITLE, $storeId);
	}

	/**
	 * Gets privacy statement configuration status for PayPal Fastlane.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function showPrivacyStatement($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_PRIVACY_STATEMENT, $storeId);
	}
}
