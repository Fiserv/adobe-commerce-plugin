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

    /**
     * @param Context $context
     * @param CredentialsRequest $credentialsRequest
     * @param MultiLevelLogger $logger
     * @param CustomerSession|null $customerSession
     */
    public function __construct(
        Context $context,
        CredentialsRequest $credentialsRequest,
        MultiLevelLogger $logger,
        CustomerSession $customerSession = null
    ) {
        parent::__construct($context);
        $this->credentialsRequest = $credentialsRequest;
        $this->logger = $logger;
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

            // Add customer id if logged in
            if ($this->customerSession !== null && $this->customerSession->isLoggedIn()) {
                $sessionData['customer']['id'] = $this->customerSession->getCustomerId();
            }

            // Intent determination (tokenization vs. primary transaction Authorize/Capture)
            $credentials = $this->credentialsRequest->requestCredentials($sessionData);
            $intent = $credentials['intent'] ?? null;
            unset($credentials['intent']);

            $response->setData([
                'ch_credentials' => $credentials,
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
}
