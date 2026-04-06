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
	const KEY_ACTIVE = 'payment_active';
	const KEY_TITLE = 'venmo';
	const KEY_SORT_ORDER = 'sort_order';
	const VENMO_BUTTON_SHAPE_KEY = 'venmo_button_shape';
	const VENMO_BUTTON_COLOR_KEY = 'venmo_button_color';
	const KEY_MERCHANT_INTEGRATOR = 'merchant_integrator';
	const KEY_STANDALONE_SPA = 'standalone_spa';
	const KEY_PAYMENT_ACTION = 'payment_action';

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

	public function getVenmoButtonColor($storeId = null)
	{
		return $this->getValue(self::VENMO_BUTTON_COLOR_KEY, $storeId);
	}

	public function getMerchantIntegrator($storeId = null)
	{
		return $this->getValue(self::KEY_MERCHANT_INTEGRATOR, $storeId);
	}

	public function getPaymentAction($storeId = null)
	{
		return $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}
}
