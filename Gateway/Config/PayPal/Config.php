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
    const KEY_TITLE = 'paypal';
    const KEY_SORT_ORDER = 'sort_order';
	const PAYPAL_BUTTON_COLOR_KEY = 'paypal_button_color';
	const PAYPAL_BUTTON_SHAPE_KEY = 'paypal_button_shape';
	const PAYPAL_BUTTON_LABEL_KEY = 'paypal_button_label';
	const KEY_MERCHANT_INTEGRATOR = 'merchant_integrator';
	const KEY_VAULT_ACTIVE = 'paypal_enable_vaulting';
	const KEY_FASTLANE_ACTIVE = 'ch_paypal_fastlane';
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

    public function getMerchantIntegrator($storeId = null)
    {
        return $this->getValue(self::KEY_MERCHANT_INTEGRATOR, $storeId);
    }

    public function isVaultActive($storeId = null)
    {
        return (bool)$this->getValue(self::KEY_VAULT_ACTIVE, $storeId);
    }

    public function isFastlaneActive($storeId = null)
    {
	    return (bool)$this->getValue(self::KEY_FASTLANE_ACTIVE, $storeId);
    }

    public function getPaymentAction($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
    }

}