<?php

namespace Fiserv\Payments\Block\Adminhtml\OpenRefund;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as GatewayConfig;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider;
use Fiserv\Payments\Model\Config\ConfigProvider as FiservConfigProvider;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;

class FormInit extends Template
{
    public function __construct(
        Context $context,
        private readonly ConfigProvider $configProvider,
        private readonly FiservConfigProvider $fiservConfigProvider,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getPaymentConfigJson(): string
    {
        $chConfig = $this->configProvider->getConfig()['payment'][ConfigProvider::CODE];
        $fconfig  = $this->fiservConfigProvider->getConfig()['payment'][FiservConfigProvider::CODE];
        $storeId  = $fconfig[FiservConfigProvider::STORE_ID_KEY];

        return $this->json->serialize([
            ConfigProvider::ENV_KEY              => $chConfig[ConfigProvider::ENV_KEY],
            ConfigProvider::MERCHANT_ID_KEY      => $chConfig[ConfigProvider::MERCHANT_ID_KEY],
            ConfigProvider::API_KEY_KEY          => $chConfig[ConfigProvider::API_KEY_KEY],
            ConfigProvider::TERMINAL_ID_KEY      => $chConfig[ConfigProvider::TERMINAL_ID_KEY],
            FiservConfigProvider::STORE_URL_KEY  => $fconfig[FiservConfigProvider::STORE_URL_KEY],
            ConfigProvider::CARD_FORM_CONFIG_KEY => $this->configProvider->buildFormConfig(GatewayConfig::KEY_SDC_ADMIN, $storeId),
            ConfigProvider::INVALID_FIELDS_KEY   => $this->configProvider->getInvalidFieldMessages(GatewayConfig::KEY_SDC_ADMIN, $storeId),
        ]);
    }
}

