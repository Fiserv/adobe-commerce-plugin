<?php
namespace Fiserv\Payments\Model\Adapter\PayPal;

use Fiserv\Payments\Lib\PayPal\Model\TokenizationRequest as TokenRequest;
use Fiserv\Payments\Lib\PayPal\Model\PaymentSession;
use Fiserv\Payments\Lib\PayPal\Model\MerchantDetails;
use Fiserv\Payments\Gateway\Request\PayPal\SessionSourceDataBuilder;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Gateway\Config\PayPal\Config;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Magento\Store\Model\StoreManagerInterface;

class PayPalTokenizationRequest
{
    protected $logger;
    protected $payPalConfig;
    protected $commerceHubConfig;
    protected $storeManager;

    public function __construct(
        MultiLevelLogger $logger,
        Config $payPalConfig,
        CommerceHubConfig $commerceHubConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->logger = $logger;
        $this->payPalConfig = $payPalConfig;
        $this->commerceHubConfig = $commerceHubConfig;
        $this->storeManager = $storeManager;
    }

    public function getTokenizationPayload($sessionId)
    {
        $this->logger->logInfo(1, "Initiating PayPal Tokenization Request");
        $data = $this->buildTokenizationPayload($sessionId);
        return $data;
    }

    private function buildTokenizationPayload($sessionId)
    {
        // Build the payload for tokenization
        $tokenizationRequest = new TokenRequest();
        $tokenizationRequest->setSessionId($sessionId);
        
        // Add merchant details
        $merchantDetails = new MerchantDetails();
        $merchantDetails->setMerchantId($this->commerceHubConfig->getMerchantId());
        $tokenizationRequest->setMerchantDetails($merchantDetails);
        
        // Add other necessary fields to the request
        $tokenizationRequest->setApiKey($this->payPalConfig->getApiKey());
        $tokenizationRequest->setApiSecret($this->payPalConfig->getApiSecret());
        
        return $tokenizationRequest;
    }
}
