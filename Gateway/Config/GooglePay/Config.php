<?php

namespace Fiserv\Payments\Gateway\Config\GooglePay;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    const CODE = 'fiserv_googlepay';
    const KEY_ACTIVE = 'payment_active';
    const KEY_TITLE = 'title';
    const KEY_PAYMENT_ACTION = 'payment_action';
    const GOOGLEPAY_BUTTON_COLOR_KEY = 'googlepay_button_style';
    const GOOGLEPAY_BUTTON_TYPE_KEY  = 'googlepay_button_type';


    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Serialize\Serializer\Json       $serializer = null
    )
    {
        parent::__construct($scopeConfig, self::CODE, self::DEFAULT_PATH_PATTERN);
    }

    /**
     * Is Google Pay active
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive($storeId = null): bool
    {
        return (bool)$this->getValue(self::KEY_ACTIVE, $storeId);
    }

    /**
     * Get Google Pay title
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTitle($storeId = null): string
    {
        return (string)$this->getValue(self::KEY_TITLE, $storeId);
    }

    /**
     * Get Google Pay payment action
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPaymentAction($storeId = null): string
    {
        return (string)$this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
    }

    public function getGooglePayButtonColor($storeId = null): string
    {
        return (string)($this->getValue(self::GOOGLEPAY_BUTTON_COLOR_KEY, $storeId) ?: 'black');
    }

    public function getGooglePayButtonType($storeId = null): string
    {
        return (string)($this->getValue(self::GOOGLEPAY_BUTTON_TYPE_KEY, $storeId) ?: 'buy');
    }

}

