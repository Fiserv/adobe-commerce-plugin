<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Config\EbtOnline;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Fiserv\Payments\Gateway\Config\EbtOnline\Config as EbtOnlineConfig;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as ChConfigProvider;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'fiserv_ebt_online';

    private EbtOnlineConfig $config;
    private ChConfig $chConfig;
    private SessionManagerInterface $session;
    private StoreManagerInterface $storeManager;

    public function __construct(
        EbtOnlineConfig $config,
        ChConfig $chConfig,
        SessionManagerInterface $session,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->chConfig = $chConfig;
        $this->session = $session;
        $this->storeManager = $storeManager;
    }

    public function getConfig(): array
    {
        $storeId = $this->session->getStoreId();

        $config = [
            ChConfigProvider::IS_ACTIVE_KEY => $this->config->isActive($storeId),
            ChConfigProvider::PAYMENT_ACTION_KEY => $this->config->getPaymentAction($storeId),
            ChConfigProvider::CARD_FORM_CONFIG_KEY => $this->buildEbtOnlineFormConfig($storeId),
            'showExpiration' => $this->config->showExpirationFields($storeId),
        ];

        return ['payment' => [self::CODE => $config]];
    }

    private function buildEbtOnlineFormConfig(?int $storeId): array
    {
        $cardNumberConfig = $this->config->cardNumberFormConfig($storeId);
        $ebtSubCategoryConfig = $this->config->ebtSubCategoryFormConfig($storeId);

        $fieldsConfig = [
            'paymentMethod' => 'EBT_ONLINE',
            'fields' => [
                'cardNumber' => [
                    'parentElementId' => $this->resolveParentElement($cardNumberConfig, 'fiserv_ebt_online-card-number'),
                    'placeholder' => $cardNumberConfig[EbtOnlineConfig::KEY_SDC_PLACEHOLDER] ?? 'EBT Card Number',
                    'enableFormatting' => true,
                    'masking'=>
                        [
                        'character' => $cardNumberConfig[EbtOnlineConfig::KEY_SDC_MASK_CHAR] ?? '•',
                        'mode' => $cardNumberConfig[EbtOnlineConfig::KEY_SDC_MASK_MODE] ?? 'ALWAYS_MASK_EXCEPT_LAST_4',
                        'shrunkLength' => 4,
                    ],
                ],
                'ebtSubCategory' => [
                    'parentElementId' => $this->resolveParentElement($ebtSubCategoryConfig, 'fiserv_ebt_online-ebt-sub-category'),
                ],
                'ebtPin' => new \stdClass(),
            ],
        ];

        // Optionally include expiration month/year dropdowns when enabled in admin
        if ($this->config->showExpirationFields($storeId)) {
            $expirationMonthConfig = $this->config->expirationMonthFormConfig($storeId);
            $expirationYearConfig  = $this->config->expirationYearFormConfig($storeId);

            $fieldsConfig['fields']['expirationMonth'] = [
                'parentElementId' => $this->resolveParentElement($expirationMonthConfig, 'fiserv_ebt_online-expiration-month'),
                'placeholder' => $expirationMonthConfig[EbtOnlineConfig::KEY_SDC_PLACEHOLDER] ?? 'Expiration Month',
            ];
            $fieldsConfig['fields']['expirationYear'] = [
                'parentElementId' => $this->resolveParentElement($expirationYearConfig, 'fiserv_ebt_online-expiration-year'),
                'placeholder' => $expirationYearConfig[EbtOnlineConfig::KEY_SDC_PLACEHOLDER] ?? 'Expiration Year',
            ];
        }

        $css = json_decode($this->chConfig->cssFormConfig(ChConfig::KEY_SDC_CHECKOUT, $storeId) ?? '{}');
        if ($css) {
            $fieldsConfig['css'] = $css;
        }

        $fontConfig = $this->chConfig->fontFormConfig(ChConfig::KEY_SDC_CHECKOUT, $storeId) ?? [];
        if ($this->hasRequiredFontConfig($fontConfig)) {
            $fieldsConfig['font'] = $fontConfig;
        }

        return $fieldsConfig;
    }

    private function hasRequiredFontConfig(array $fontConfig): bool
    {
        return
            !empty(trim((string) ($fontConfig[ChConfig::KEY_FONT_DATA]   ?? ''))) &&
            !empty(trim((string) ($fontConfig[ChConfig::KEY_FONT_FAMILY] ?? ''))) &&
            !empty(trim((string) ($fontConfig[ChConfig::KEY_FONT_FORMAT] ?? '')));
    }

    private function resolveParentElement(array $fieldConfig, string $fallback): string
    {
        $parentElementId = trim((string) ($fieldConfig[EbtOnlineConfig::KEY_SDC_PARENT_ELEMENT] ?? ''));
        return $parentElementId !== '' ? $parentElementId : $fallback;
    }
}

