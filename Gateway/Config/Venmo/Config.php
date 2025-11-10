<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\Venmo;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	// Config keys (adjust as needed for venmo)
	const KEY_ACTIVE = 'active';
	const KEY_TITLE = 'venmo';
	const KEY_SORT_ORDER = 'sort_order';
	const VENMO_BUTTON_SHAPE_KEY = 'venmo_button_shape';
	const KEY_MERCHANT_INTEGRATOR = 'merchant_integrator';
	const KEY_STANDALONE_SPA = 'standalone_spa';
	const KEY_DEBUG = 'debug';
	const KEY_PAYMENT_ACTION = 'payment_action';
	const KEY_MERCHANT_ID = 'merchant_id';
	const KEY_TERMINAL_ID = 'terminal_id';
	const KEY_API_KEY = 'api_key';
	const KEY_API_SECRET = 'api_secret';
	const KEY_ENVIRONMENT = 'api_environment';

	/**
	 * @var \Magento\Framework\Serialize\Serializer\Json
	 */
	private $serializer;

	private $scopeConfig;

	/**
	 * Fiserv CommerceHub config constructor
	 *
	 * @param ScopeConfigInterface $scopeConfig
	 * @param null|string $methodCode
	 * @param string $pathPattern
	 * @param Json|null $serializer
	 */
	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
														   $methodCode = 'fiserv_venmo',
														   $pathPattern = self::DEFAULT_PATH_PATTERN,
		Json $serializer = null
	) {
		parent::__construct($scopeConfig, $methodCode, $pathPattern);
		$this->scopeConfig = $scopeConfig;
		$this->serializer = $serializer ?: new Json();
	}

	public function isActive($storeId = null)
	{
		return (bool)$this->getValue(self::KEY_ACTIVE, $storeId);
	}

	public function getTitle($storeId = null)
	{
		return $this->getValue(self::KEY_TITLE, $storeId);
	}

	public function getVenmoButtonShape($storeId = null)
	{
		return $this->getValue(self::VENMO_BUTTON_SHAPE_KEY, $storeId);
	}

	public function getMerchantIntegrator($storeId = null)
	{
		return $this->getValue(self::KEY_MERCHANT_INTEGRATOR, $storeId);
	}

	public function isFastlaneActive($storeId = null)
	{
		return (bool)$this->getValue(self::KEY_FASTLANE_ACTIVE, $storeId);
	}

	public function isDebug($storeId = null)
	{
		return (bool)$this->getValue(self::KEY_DEBUG, $storeId);
	}

	public function getPaymentAction($storeId = null)
	{
		return $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}

	/**
	 * Returns Venmo merchant id.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getMerchantId($storeId = null)
	{
		return $this->getValue(self::KEY_MERCHANT_ID, $storeId);
	}

	/**
	 * Returns Venmo terminal id.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getTerminalId($storeId = null)
	{
		return $this->getValue(self::KEY_TERMINAL_ID, $storeId);
	}

	/**
	 * Returns Venmo API key.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getApiKey($storeId = null)
	{
		return $this->getValue(self::KEY_API_KEY, $storeId);
	}

	/**
	 * Returns Venmo API secret.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getApiSecret($storeId = null)
	{
		return $this->getValue(self::KEY_API_SECRET, $storeId);
	}

	/**
	 * Gets value of Venmo API environment.
	 *
	 * Possible values: CERT or PROD.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getApiEnvironment($storeId = null)
	{
		return $this->getValue(self::KEY_ENVIRONMENT, $storeId);
	}
}
