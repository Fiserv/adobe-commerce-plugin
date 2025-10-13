<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Controller\CommerceHub;

use Fiserv\Payments\Model\Adapter\CommerceHub\CredentialsRequest;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Webapi\Exception;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Customer\Model\Session as CustomerSession;
use Fiserv\Payments\Model\PayPal\ProviderCustomerIdFactory;
use Fiserv\Payments\Gateway\Config\PayPal\Config as PayPalConfig;

/**
 * Class GetCredentials
 */
class GetCredentials extends Action implements HttpPostActionInterface
{
    const HTTP_UNAUTHORIZED = 401;
    const KEY_STORE_ID = "store_id";

    /**
     * @var CredentialsRequest
     */
    private $chAdapter;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var ProviderCustomerIdFactory
     */
    private $providerCustomerIdFactory;

    /**
     * @var PayPalConfig
     */
    private $paypalConfig;

    /**
    * @param Context $context
    * @param CredentialsRequest $chAdapter
    * @param MultiLevelLogger $logger
    * @param CustomerSession $customerSession
    * @param ProviderCustomerIdFactory $providerCustomerIdFactory
    * @param PayPalConfig $paypalConfig
    */
    public function __construct(
        Context $context,
        CredentialsRequest $chAdapter,
        MultiLevelLogger $logger,
        CustomerSession $customerSession = null,
        ProviderCustomerIdFactory $providerCustomerIdFactory = null,
        PayPalConfig $paypalConfig = null
    ) {
        parent::__construct($context);
        $this->chAdapter = $chAdapter;
        $this->logger = $logger;
        $this->customerSession = $customerSession;
        $this->providerCustomerIdFactory = $providerCustomerIdFactory;
        $this->paypalConfig = $paypalConfig;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
		$response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $data = $this->getRequest()->getContent();
            $sessionData = json_decode($data, true) ?? array();
            // Add customer id if logged in
            if ($this->customerSession !== null && $this->customerSession->isLoggedIn()) {
                $sessionData['customer']['id'] = $this->customerSession->getCustomerId();
                // Only add providerCustomerId if vaulting is active (PayPal logic)
                if ($this->paypalConfig !== null && $this->paypalConfig->isVaultActive()) {
                    $customerId = $this->customerSession->getCustomerId();
                    try {
                        $providerCustomerIdModel = $this->providerCustomerIdFactory->create()->getCollection()
                            ->addFieldToFilter('customer_id', $customerId)
                            ->getFirstItem();
                        if ($providerCustomerIdModel->getId()) {
                            $storedProviderCustomerId = $providerCustomerIdModel->getPaypalProviderCustomerId();
                            if ($storedProviderCustomerId) {
                                $sessionData['customer']['providerCustomerId'] = $storedProviderCustomerId;
                                $this->logger->logInfo(1, "Using stored providerCustomerId for customer " . $customerId);
                            }
                        }
                    } catch (\Exception $e) {
                        $this->logger->logError(1, "Error retrieving providerCustomerId: " . $e->getMessage());
                    }
                }
            }
            $response->setData(['ch_credentials' => $this->chAdapter->requestCredentials($sessionData)]);
        } catch (\Exception $e) {
			$this->logger->logCritical(1, "An error occured in the retrieval of credentials");
            $this->logger->logCritical(2, $e);
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
