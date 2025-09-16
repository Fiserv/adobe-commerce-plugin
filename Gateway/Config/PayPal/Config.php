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
    const KEY_VAULT_ACTIVE = 'paypal_enable_vaulting';
    const KEY_VENMO_ACTIVE = 'fiserv_paypal_venmo';
	const KEY_TOKEN_STRATEGY = 'tokenization_strategy';
	const KEY_TOKENIZATION = "tokenization";
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
        return (bool)$this->getValue(self::KEY_ACTIVE, $storeId);
    }

    public function showPrivacyStatement($storeId = null)
    {
        return (bool) $this->getValue(self::SHOW_PRIVACY_STATEMENT_KEY, $storeId);
    }

    public function getPaypalButtonColor($storeId = null)
    {
        return $this->getValue(self::PAYPAL_BUTTON_COLOR_KEY, $storeId);
    }

    public function getPaypalButtonShape($storeId = null)
    {
        return $this->getValue(self::PAYPAL_BUTTON_SHAPE_KEY, $storeId);
    }

    public function getPaypalButtonLabel($storeId = null)
    {
        return $this->getValue(self::PAYPAL_BUTTON_LABEL_KEY, $storeId);
    }

    public function getVenmoButtonColor($storeId = null)
    {
        return $this->getValue(self::VENMO_BUTTON_COLOR_KEY, $storeId);
    }

    public function getVenmoButtonShape($storeId = null)
    {
        return $this->getValue(self::VENMO_BUTTON_SHAPE_KEY, $storeId);
    }

    public function getMerchantIntegrator($storeId = null)
    {
        return $this->getValue(self::KEY_MERCHANT_INTEGRATOR, $storeId);
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

    public function getPaymentAction($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
    }

    /**
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getTokenStrategy($storeId = null)
    {
        return $this->getValue(self::KEY_TOKEN_STRATEGY, $storeId);
    }

    /**
     * Returns PayPal merchant id.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getMerchantId($storeId = null)
    {
        return $this->getValue(self::KEY_MERCHANT_ID, $storeId);
    }

    /**
     * Returns PayPal terminal id.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTerminalId($storeId = null)
    {
        return $this->getValue(self::KEY_TERMINAL_ID, $storeId);
    }

    /**
     * Returns PayPal API key.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiKey($storeId = null)
    {
        return $this->getValue(self::KEY_API_KEY, $storeId);
    }

    /**
     * Returns PayPal API secret.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiSecret($storeId = null)
    {
        return $this->getValue(self::KEY_API_SECRET, $storeId);
    }

    /**
     * Gets value of PayPal API environment.
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
