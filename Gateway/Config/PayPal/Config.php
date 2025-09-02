<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\PayPal;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
    // Config keys (adjust as needed for PayPal)
    const KEY_ACTIVE = 'active';
    const KEY_TITLE = 'title';
    const KEY_SORT_ORDER = 'sort_order';
	const SHOW_PRIVACY_STATEMENT_KEY = "show_privacy_statement";
	const PAYPAL_BUTTON_COLOR_KEY = "paypal_button_color";
	const PAYPAL_BUTTON_SHAPE_KEY = "paypal_button_shape";
	const PAYPAL_BUTTON_LABEL_KEY = "paypal_button_label";
	const VENMO_BUTTON_COLOR_KEY = "venmo_button_color";
	const VENMO_BUTTON_SHAPE_KEY = "venmo_button_shape";
	const KEY_MERCHANT_INTEGRATOR = 'merchant_integrator';
    const KEY_VAULT_ACTIVE = 'vault_active';
    const KEY_VENMO_ACTIVE = 'venmo_active';
	const KEY_TOKEN_STRATEGY = 'tokenization_strategy';
	const KEY_TOKENIZATION = "tokenization";
	const KEY_STANDALONE_SPA = 'standalone_spa';
	const KEY_DEBUG = 'debug';

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
        $methodCode = 'fiserv_paypal',
        $pathPattern = self::DEFAULT_PATH_PATTERN,
        Json $serializer = null
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->scopeConfig = $scopeConfig;
        $this->serializer = $serializer ?: new Json();
    }

    public function isActive($storeId = null)
    {
        // DEBUG: Force PayPal to always be active for troubleshooting
        return (bool)$this->getValue(self::KEY_ACTIVE, $storeId);
        // return true;
    }

    /**
	 * Gets privacy statement configuration status.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function showPrivacyStatement($storeId = null)
	{
		return (bool) $this->getValue(self::SHOW_PRIVACY_STATEMENT_KEY, $storeId);
	}

    /**
     * Gets PayPal button color config value.
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getPaypalButtonColor($storeId = null)
    {
        return $this->getValue(self::PAYPAL_BUTTON_COLOR_KEY, $storeId);
    }

    /**
     * Gets PayPal button shape config value.
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getPaypalButtonShape($storeId = null)
    {
        return $this->getValue(self::PAYPAL_BUTTON_SHAPE_KEY, $storeId);
    }

    /**
     * Gets PayPal button label config value.
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getPaypalButtonLabel($storeId = null)
    {
        return $this->getValue(self::PAYPAL_BUTTON_LABEL_KEY, $storeId);
    }

    /**
     * Gets Venmo button color config value.
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getVenmoButtonColor($storeId = null)
    {
        return $this->getValue(self::VENMO_BUTTON_COLOR_KEY, $storeId);
    }

    /**
     * Gets Venmo button shape config value.
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getVenmoButtonShape($storeId = null)
    {
        return $this->getValue(self::VENMO_BUTTON_SHAPE_KEY, $storeId);
    }

    /**
	 * Returns software integrator used by merchant.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getMerchantIntegrator($storeId = null)
	{
		return $this->getValue(self::KEY_MERCHANT_INTEGRATOR, $storeId);
	}

	/**
	 * Gets value of CommerceHub API environment.
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


    public function getTitle($storeId = null)
    {
        return $this->getValue(self::KEY_TITLE, $storeId);
    }

    public function getSortOrder($storeId = null)
    {
        return $this->getValue(self::KEY_SORT_ORDER, $storeId);
    }

    public function isVaultActive($storeId = null)
    {
        return (bool)$this->getValue(self::KEY_VAULT_ACTIVE, $storeId);
    }

    public function isVenmoActive($storeId = null)
    {
        return (bool)$this->getValue(self::KEY_VENMO_ACTIVE, $storeId);
    }

    public function isDebug($storeId = null)
    {
        return (bool)$this->getValue(self::KEY_DEBUG, $storeId);
    }
}
