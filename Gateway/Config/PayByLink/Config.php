<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\PayByLink;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Fiserv\Payments\Helper\PayByLink\ExpiryTimeConverter;

/**
 * Pay By Link Config Class
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
    const KEY_ACTIVE = 'active';
    const KEY_PAYMENT_ACTION = 'payment_action';
    const KEY_MAX_EXPIRY = 'max_expiry';
    const KEY_EXPIRY_TIME = 'expiry_time';
    const KEY_PAYMENT_PAGE_ID = 'payment_page_id';
    const KEY_OPTIONAL_NOTE = 'optional_note';

    /**
     * @var Json
     */
    private $serializer;

    private $scopeConfig;

    /**
     * Config constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param string|null $methodCode
     * @param string $pathPattern
     * @param Json|null $serializer
     */
    public function __construct(
		ScopeConfigInterface $scopeConfig,
		$methodCode = "fiserv_pay_by_link",
		$pathPattern = self::DEFAULT_PATH_PATTERN,
		?Json $serializer = null
	) {
		parent::__construct($scopeConfig, $methodCode, $pathPattern);
		$this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()
		       ->get(Json::class);
		$this->scopeConfig = $scopeConfig;
	}

    /**
     * Get Pay By Link active status
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
    }

    /**
     * Get charge type (Authorize or Sale)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPaymentAction($storeId = null)
    {
        return (string) ($this->getValue(self::KEY_PAYMENT_ACTION, $storeId) ?: \Magento\Payment\Model\MethodInterface::ACTION_AUTHORIZE);
    }

    /**
     * Get expiry time in minutes (converted from hours and minutes)
     *
     * @param int|null $storeId
     * @return int
     */
    public function getExpiryTime($storeId = null)
    {
	    $expiryTimeValue = $this->getValue(self::KEY_EXPIRY_TIME, $storeId);
        
        if (empty($expiryTimeValue)) {
            // Default: 4 hours = 240 minutes
            return 240;
        }

        try {
            $expiryData = $this->serializer->unserialize($expiryTimeValue);

            // Convert to total minutes (days + hours + minutes); default to 4 hours
            $totalMinutes = ExpiryTimeConverter::configToMinutes($expiryData, ['hours' => 4]);
            
            // Maximum 4 hours = 240 minutes
            if ($totalMinutes > 240) {
                $totalMinutes = 240;
            }
            
            return $totalMinutes;
        } catch (\Exception $e) {
            // Return default if there's an error
            return 240;
        }
    }

	public function getMaxExpiry($storeId = null)
	{
		return $this->getValue(self::KEY_MAX_EXPIRY, $storeId);
	}

	/**
     * Get payment page ID
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getPaymentPageId($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENT_PAGE_ID, $storeId);
    }

    /**
     * Check if optional note is enabled
     *
     * @param int|null $storeId
     * @return bool
     */    public function isOptionalNoteEnabled($storeId = null)
     {
        return (bool) $this->getValue(self::KEY_OPTIONAL_NOTE, $storeId);
    }
}
