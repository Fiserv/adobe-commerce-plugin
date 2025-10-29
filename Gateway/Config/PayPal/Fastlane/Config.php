<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\PayPal\Fastlane;

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
        $methodCode = 'fiserv_paypal_fastlane',
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
}
