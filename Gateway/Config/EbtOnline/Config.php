<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\EbtOnline;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    const KEY_ACTIVE = 'payment_active';
    const KEY_PAYMENT_ACTION = 'payment_action';
    const KEY_SDC_PARENT_ELEMENT = 'parent_element';
    const KEY_SDC_PLACEHOLDER = 'placeholder';
    const KEY_SDC_MASK_CHAR = 'masking_character';
    const KEY_SDC_MASK_MODE = 'masking_mode';

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        $methodCode = 'fiserv_ebt_online',
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
    }

    public function isActive($storeId = null): bool
    {
        return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
    }

    public function getPaymentAction($storeId = null): string
    {
        return (string) $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
    }

    public function cardNumberFormConfig($storeId = null): array
    {
        return [
            self::KEY_SDC_PARENT_ELEMENT => $this->getValue('sdc_custom_checkout_card_number_' . self::KEY_SDC_PARENT_ELEMENT, $storeId),
            self::KEY_SDC_PLACEHOLDER => $this->getValue('sdc_custom_checkout_card_number_' . self::KEY_SDC_PLACEHOLDER, $storeId),
            self::KEY_SDC_MASK_CHAR => $this->getValue('sdc_custom_checkout_card_number_' . self::KEY_SDC_MASK_CHAR, $storeId),
            self::KEY_SDC_MASK_MODE => $this->getValue('sdc_custom_checkout_card_number_' . self::KEY_SDC_MASK_MODE, $storeId),
        ];
    }

    public function ebtSubCategoryFormConfig($storeId = null): array
    {
        return [
            self::KEY_SDC_PARENT_ELEMENT => $this->getValue('sdc_custom_checkout_ebt_sub_category_' . self::KEY_SDC_PARENT_ELEMENT, $storeId),
        ];
    }

    public function showExpirationFields($storeId = null): bool
    {
        return (bool) $this->getValue('sdc_custom_checkout_show_expiration', $storeId);
    }

    public function expirationMonthFormConfig($storeId = null): array
    {
        return [
            self::KEY_SDC_PARENT_ELEMENT => $this->getValue('sdc_custom_checkout_expiration_month_' . self::KEY_SDC_PARENT_ELEMENT, $storeId),
            self::KEY_SDC_PLACEHOLDER => $this->getValue('sdc_custom_checkout_expiration_month_' . self::KEY_SDC_PLACEHOLDER, $storeId),
        ];
    }

    public function expirationYearFormConfig($storeId = null): array
    {
        return [
            self::KEY_SDC_PARENT_ELEMENT => $this->getValue('sdc_custom_checkout_expiration_year_' . self::KEY_SDC_PARENT_ELEMENT, $storeId),
            self::KEY_SDC_PLACEHOLDER => $this->getValue('sdc_custom_checkout_expiration_year_' . self::KEY_SDC_PLACEHOLDER, $storeId),
        ];
    }
}

