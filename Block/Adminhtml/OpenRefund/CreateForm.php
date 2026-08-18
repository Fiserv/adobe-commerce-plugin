<?php

declare(strict_types=1);

namespace Fiserv\Payments\Block\Adminhtml\OpenRefund;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as GatewayConfig;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider;
use Magento\Backend\Block\Template;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;

class CreateForm extends Template
{
    /** @var GatewayConfig */
    private $gatewayConfig;

    /** @var ConfigProvider */
    private $configProvider;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var Json */
    private $json;

    public function __construct(
        Template\Context $context,
        GatewayConfig $gatewayConfig,
        ConfigProvider $configProvider,
        StoreManagerInterface $storeManager,
        Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->gatewayConfig   = $gatewayConfig;
        $this->configProvider  = $configProvider;
        $this->storeManager    = $storeManager;
        $this->json            = $json;
    }

    public function getSubmitUrl(): string
    {
        return $this->getUrl('fiserv/openrefund/create');
    }

    public function getIndexUrl(): string
    {
        return $this->getUrl('fiserv/openrefund/index');
    }

    public function getCustomerSearchUrl(): string
    {
        return $this->getUrl('fiserv/openrefund/customersearch');
    }

    public function getCustomerTokensUrl(): string
    {
        return $this->getUrl('fiserv/openrefund/getcustomertokens');
    }

    public function getTokenizeSessionUrl(): string
    {
        return $this->getUrl('fiserv/openrefund/tokenizesession');
    }

    public function isEnabled(): bool
    {
        return $this->gatewayConfig->isOpenRefundEnabled();
    }

    public function getDefaultCaptureFlag(): int
    {
        return (int)$this->gatewayConfig->getOpenRefundCaptureFlag();
    }

    public function getMaxAmount(): float
    {
        return (float)$this->gatewayConfig->getOpenRefundMaxAmount();
    }

    public function getAcceptedCurrencyCodes(): array
    {
	    $configured = (string)$this->gatewayConfig->getCurrency();
	    $parts = preg_split('/[\s,]+/', strtoupper(trim($configured))) ?: [];

	    $codes = [];
	    foreach ($parts as $part) {
		    if ($part !== '' && preg_match('/^[A-Z]{3}$/', $part)) {
			    $codes[] = $part;
		    }
	    }

	    $codes = array_values(array_unique($codes));
	    return $codes !== [] ? $codes : ['USD'];
    }

    public function getDefaultCurrencyCode(): string
    {
	    $codes = $this->getAcceptedCurrencyCodes();
	    return (string)($codes[0] ?? 'USD');
    }

    /**
     * Returns JSON config consumed by the open-refund SDK JS component.
     * Mirrors the pattern used in AdminHtml\Order\Create\Billing\Method\Form::getConfig().
     */
    public function getPaymentConfig(): string
    {
        $storeId = (int)$this->storeManager->getStore()->getId();

        $config = [
            ConfigProvider::ENV_KEY         => $this->gatewayConfig->getApiEnvironment(),
            ConfigProvider::MERCHANT_ID_KEY => $this->gatewayConfig->getMerchantId(),
            ConfigProvider::TERMINAL_ID_KEY => $this->gatewayConfig->getTerminalId(),
            ConfigProvider::API_KEY_KEY     => $this->gatewayConfig->getApiKey(),
            'storeUrl'                      => $this->storeManager->getStore()->getBaseUrl(),
            'formConfig'                    => $this->configProvider->buildFormConfig(
                GatewayConfig::KEY_SDC_ADMIN,
                $storeId
            ),
            'invalidFields'                 => $this->configProvider->getInvalidFieldMessages(
                GatewayConfig::KEY_SDC_ADMIN,
                $storeId
            ),
        ];


        return $this->json->serialize($config);
    }
}
