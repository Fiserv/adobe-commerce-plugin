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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Webapi\Exception;
use Magento\Store\Model\ScopeInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Customer\Model\Session as CustomerSession;
use Fiserv\Payments\Model\PayPal\ProviderCustomerIdFactory;

/**
 * Creates a CommerceHub payment session via the v1/sessions/create API.
 */
class CreateSession extends Action implements HttpPostActionInterface
{
    const HTTP_UNAUTHORIZED = 401;
    const KEY_STORE_ID = "store_id";

    /** @var CredentialsRequest */
    private $credentialsRequest;

    /** @var MultiLevelLogger */
    private $logger;

    /** @var CustomerSession */
    private $customerSession;

    /** @var ProviderCustomerIdFactory */
    private $providerCustomerIdFactory;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /**
     * @param Context $context
     * @param CredentialsRequest $credentialsRequest
     * @param MultiLevelLogger $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerSession|null $customerSession
     * @param ProviderCustomerIdFactory|null $providerCustomerIdFactory
     */
    public function __construct(
        Context $context,
        CredentialsRequest $credentialsRequest,
        MultiLevelLogger $logger,
        ScopeConfigInterface $scopeConfig,
        CustomerSession $customerSession = null,
        ProviderCustomerIdFactory $providerCustomerIdFactory = null
    ) {
        parent::__construct($context);
        $this->credentialsRequest = $credentialsRequest;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->customerSession = $customerSession;
        $this->providerCustomerIdFactory = $providerCustomerIdFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
		$response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $data = $this->getRequest()->getContent();
            $sessionData = json_decode($data, true) ?? [];

            // Derive the session intent from the payment method's server-side config.
            // The JS passes paymentCode so we can look up the correct payment_action
            // without exposing intent derivation logic to the client.
            $paymentCode = $sessionData['paymentCode'] ?? null;
            unset($sessionData['paymentCode']); // not a CommerceHub API field
            if ($paymentCode) {
                $sessionData['session']['intent'] = $this->getIntentForPaymentMethod($paymentCode);
            }

            // Add customer id if logged in
            if ($this->customerSession !== null && $this->customerSession->isLoggedIn()) {
                $sessionData['customer']['id'] = $this->customerSession->getCustomerId();
                // Add providerCustomerId when available (e.g. PayPal vault)
                if ($this->providerCustomerIdFactory !== null) {
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

            $response->setData(['ch_credentials' => $this->credentialsRequest->requestCredentials($sessionData)]);
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

    /**
     * Derive the CommerceHub session intent from a payment method's store configuration.
     * Returns INTENT_CAPTURE when payment_action is authorize_capture, otherwise INTENT_AUTHORIZE.
     */
    private function getIntentForPaymentMethod(string $paymentCode): string
    {
        $paymentAction = (string) $this->scopeConfig->getValue(
            'payment/' . $paymentCode . '/payment_action',
            ScopeInterface::SCOPE_STORE
        );

        return strtolower($paymentAction) === 'authorize_capture'
            ? CredentialsRequest::INTENT_CAPTURE
            : CredentialsRequest::INTENT_AUTHORIZE;
    }
}
