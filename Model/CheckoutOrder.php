<?php

namespace Fiserv\Payments\Model;

use Fiserv\Payments\Api\CheckoutOrderInterface;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class CheckoutOrder implements CheckoutOrderInterface
{
    protected $curl;
    protected $json;
    protected $logger;
    protected $config;

    public function __construct(
        Curl $curl,
        Json $json,
        LoggerInterface $logger,
        Config $config
    ) {
        $this->curl = $curl;
        $this->json = $json;
        $this->logger = $logger;
        $this->config = $config;
    }

    protected function getApiBaseUrl()
    {
        $environment = $this->config->getApiEnvironment();
        switch (strtoupper($environment)) {
            case 'PROD':
                return $this->config->getProdApiService();
            case 'CERT':
                return $this->config->getCertApiService();
            case 'QA':
                return $this->config->getQaApiService();
            default:
                return 'https://api.commercehub.com'; // Fallback
        }
    }

    protected function getToken()
    {
        // Assuming token is the API key, adjust if needed
        return $this->config->getApiKey();
    }

    public function execute($commercehubOrderId, $action, $amount = null, $currency = null)
    {
        $action = strtolower($action);
        $path = '';

        switch ($action) {
            case 'authorize':
                $path = '/checkouts/orders/' . $commercehubOrderId . '/authorize';
                break;
            case 'capture':
                $path = '/checkouts/orders/' . $commercehubOrderId . '/capture';
                break;
            case 'refund':
                $path = '/checkouts/orders/' . $commercehubOrderId . '/refund';
                break;
            case 'void':
                $path = '/checkouts/orders/' . $commercehubOrderId . '/void';
                break;
            default:
                throw new \InvalidArgumentException('Invalid payment action: ' . $action);
        }
        $url = rtrim($this->getApiBaseUrl(), '/') . '/' . urlencode($commercehubOrderId) . '/' . $path;

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getToken(),
        ];
        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = ['value' => (string)number_format($amount, 2, '.', ''), 'currency' => $currency];
        }
        try {
            $this->curl->setHeaders($headers);
            $this->curl->post($url, $this->json->serialize($payload));
            $body = $this->curl->getBody();
            $this->logger->info('commercehub[' . $action . ']' . 'Request URL: ' . $url . ' payload: ' . print_r($payload, true));
            $this->logger->info('Payment processed successfully: ' . $body);
            $response = $this->json->unserialize($body);
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Error processing payment: ' . $e->getMessage());
            throw $e;
        }
    }
}
