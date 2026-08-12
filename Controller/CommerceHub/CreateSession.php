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

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /**
     * @param Context $context
     * @param CredentialsRequest $credentialsRequest
     * @param MultiLevelLogger $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerSession|null $customerSession
     */
    public function __construct(
        Context $context,
        CredentialsRequest $credentialsRequest,
        MultiLevelLogger $logger,
        ScopeConfigInterface $scopeConfig,
        CustomerSession $customerSession = null
    ) {
        parent::__construct($context);
        $this->credentialsRequest = $credentialsRequest;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->customerSession = $customerSession;
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

            // Route 1: Tokenization request (e.g. standalone stored-payment account creation).
            // Route 2: Primary transaction — the sub-type (Authorize vs Authorize+Capture/Sale)
            // Route2 : is derived strictly from the server to avoid any client-side manipulation
            $paymentCode = $sessionData['paymentCode'];
            $isTokenization = !empty($sessionData['isTokenization']);
            unset($sessionData['paymentCode'], $sessionData['isTokenization']);

            $intent = $isTokenization
                ? CredentialsRequest::INTENT_CHECKOUT
                : $this->getPrimaryTransactionIntent($paymentCode);

            $sessionData['session']['intent'] = $intent;

            // Add customer id if logged in
            if ($this->customerSession !== null && $this->customerSession->isLoggedIn()) {
                $sessionData['customer']['id'] = $this->customerSession->getCustomerId();
            }

            $response->setData([
                'ch_credentials' => $this->credentialsRequest->requestCredentials($sessionData),
                'session_intent' => $intent,
            ]);
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
     * Derive the primary-transaction intent from the admin panel store configuration.
     * Returns INTENT_CAPTURE when payment_action is authorize_capture (sale), otherwise INTENT_AUTHORIZE.
     */
    private function getPrimaryTransactionIntent(string $paymentCode): string
    {
        if (!$paymentCode) {
            return CredentialsRequest::INTENT_AUTHORIZE;
        }

        $paymentAction = (string) $this->scopeConfig->getValue(
            'payment/' . $paymentCode . '/payment_action',
            ScopeInterface::SCOPE_STORE
        );

        return strtolower($paymentAction) === 'authorize_capture'
            ? CredentialsRequest::INTENT_CAPTURE
            : CredentialsRequest::INTENT_AUTHORIZE;
    }
}
