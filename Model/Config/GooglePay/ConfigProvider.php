<?php

namespace Fiserv\Payments\Model\Config\GooglePay;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Fiserv\Payments\Gateway\Config\GooglePay\Config as GooglePayConfig;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Locale\ResolverInterface as LocaleResolver;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as ChConfigProvider;

/**
 * Google Pay ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'fiserv_googlepay';
    const STORE_NAME_KEY = 'storeName';
    const BUTTON_COLOR_KEY = 'googlepayButtonStyle';
    const BUTTON_TYPE_KEY  = 'googlepayButtonType';
    const LOCALE_KEY = 'locale';

    private MultiLevelLogger $logger;

    /**
     * @var GooglePayConfig
     */
    protected $config;

    /**
     * @var SessionManagerInterface
     */
    private $session;

    private $storeManager;

    private $chConfig;

    /** @var LocaleResolver */
    private $localeResolver;

    /**
     * Constructor
     *
     * @param GooglePayConfig $config
     * @param SessionManagerInterface $session
     * @param MultiLevelLogger $logger
     * @param LocaleResolver $localeResolver
     */
    public function __construct(
        GooglePayConfig         $config,
        SessionManagerInterface $session,
        StoreManagerInterface   $storeManager,
        ChConfig                $chConfig,
        MultiLevelLogger        $logger,
        LocaleResolver          $localeResolver
    )
    {
        $this->config = $config;
        $this->session = $session;
        $this->storeManager = $storeManager;
        $this->chConfig = $chConfig;
        $this->logger = $logger;
        $this->localeResolver = $localeResolver;
    }

    /**
     * Convert the resolved Magento locale (e.g. "en_US") into the IETF BCP47 format
     * (e.g. "en-US") expected downstream for wallet button localization. The
     * frontend Google Pay button component maps this value to one of Google Pay's
     * supported button languages, falling back to English when unsupported.
     *
     * @return string
     */
    private function getBcp47Locale(): string
    {
        $locale = (string) $this->localeResolver->getLocale();
        return $locale ? str_replace('_', '-', $locale) : 'en-US';
    }

    /**
     * Retrieve Google Pay checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $storeId = $this->session->getStoreId();

        $config = [
            ChConfigProvider::IS_ACTIVE_KEY => $this->config->isActive($storeId),
            ChConfigProvider::PAYMENT_ACTION_KEY => $this->config->getPaymentAction($storeId),
            self::STORE_NAME_KEY => $this->storeManager->getStore()->getName(),
            ChConfigProvider::MERCHANT_ID_KEY => $this->chConfig->getMerchantId($storeId),
            ChConfigProvider::TERMINAL_ID_KEY => $this->chConfig->getTerminalId($storeId),
            ChConfigProvider::ENV_KEY => $this->chConfig->getApiEnvironment($storeId),
            ChConfigProvider::API_KEY_KEY => $this->chConfig->getApiKey($storeId),
            self::BUTTON_COLOR_KEY => $this->config->getGooglePayButtonColor($storeId),
            self::BUTTON_TYPE_KEY  => $this->config->getGooglePayButtonType($storeId),
            self::LOCALE_KEY => $this->getBcp47Locale()
        ];
        return ['payment' => [self::CODE => $config]];
    }
}

