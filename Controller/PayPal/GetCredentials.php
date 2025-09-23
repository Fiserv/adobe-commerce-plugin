<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Controller\PayPal;

use Fiserv\Payments\Model\Adapter\PayPal\PayPalCredentialsRequest;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Webapi\Exception;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class GetPaypalCredentials
 */
class GetCredentials extends Action implements HttpPostActionInterface
{
    const HTTP_UNAUTHORIZED = 401;
    const KEY_STORE_ID = "store_id";

    /**
     * @var PayPalCredentialsRequest
     */
    private $paypalAdapter;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
    * @var ScopeConfigInterface
    */
    private $scopeConfig;

    /**
    * @param Context $context
    * @param MultiLevelLogger $logger
    * @param PayPalCredentialsRequest $paypalAdapter
    * @param ScopeConfigInterface $scopeConfig
    */
    public function __construct(
        Context $context,
        PayPalCredentialsRequest $paypalAdapter,
        MultiLevelLogger $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->paypalAdapter = $paypalAdapter;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */


    public function execute()
    {
        $this->logger->logInfo(1, "GetCredentials execute() called");
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $data = $this->getRequest()->getContent();
            $sessionData = json_decode($data, true) ?? array();
            $paypalCredentials = $this->paypalAdapter->requestCredentials($sessionData);

            // Fetch PayPal button config from admin config
            $enableVaulting = $this->scopeConfig->isSetFlag('payment/fiserv_paypal/paypal_enable_vaulting', ScopeInterface::SCOPE_STORE);
            // $enableVenmo = $this->scopeConfig->isSetFlag('payment/fiserv_paypal/fiserv_paypal_venmo', ScopeInterface::SCOPE_STORE);
            $buttons = [
                'paypal' => [
                    'parentElementId' => 'paypal-button-container',
                    'color' => $this->scopeConfig->getValue('payment/fiserv_paypal/paypal_button_color', ScopeInterface::SCOPE_STORE) ?: 'gold',
                    'shape' => $this->scopeConfig->getValue('payment/fiserv_paypal/paypal_button_shape', ScopeInterface::SCOPE_STORE) ?: 'rect',
                    'label' => $this->scopeConfig->getValue('payment/fiserv_paypal/paypal_button_label', ScopeInterface::SCOPE_STORE) ?: 'paypal',
                ]
            ];
            // if ($enableVenmo) {
            //     $buttons['venmo'] = [
            //         'parentElementId' => 'venmo-button-container',
            //         'color' => $this->scopeConfig->getValue('payment/fiserv_paypal/venmo_button_color', ScopeInterface::SCOPE_STORE) ?: 'gold',
            //         'shape' => $this->scopeConfig->getValue('payment/fiserv_paypal/venmo_button_shape', ScopeInterface::SCOPE_STORE) ?: 'rect',
            //     ];
            // }
            $paypalButtonConfig = [
                'data' => [
                    'enableVaulting' => $enableVaulting,
                    'customerConfirmation' => 'PAY_NOW',
                    'buttons' => $buttons
                ]
            ];

            $response->setData([
                'paypal_credentials' => $paypalCredentials,
                'paypal_button_config' => $paypalButtonConfig
            ]);
        } catch (\Exception $e) {
            $this->logger->logCritical(1, "An error occured in the retrieval of credentials");
            $this->logger->logCritical(2, $e->getMessage());
            return $this->processBadRequest($response);
        }

        return $response;
    }

    /**
     * Return response for bad request
     * @param ResultInterface $response
     * @return ResultInterface
     */
    private function processBadRequest(ResultInterface $response)
    {
        $response->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
        $response->setData(['message' => __('Sorry, but something went wrong')]);

        return $response;
    }

    private function validateStoreId($storeId)
    {
        return true;	    
    }
}
