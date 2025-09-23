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
    * @param Context $context
    * @param MultiLevelLogger $logger
    * @param PayPalCredentialsRequest $paypalAdapter
    */
    public function __construct(
        Context $context,
        PayPalCredentialsRequest $paypalAdapter,
        MultiLevelLogger $logger,
    ) {
        parent::__construct($context);
        $this->paypalAdapter = $paypalAdapter;
        $this->logger = $logger;
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
			$response->setData(['paypal_credentials' => $this->paypalAdapter->requestCredentials($sessionData)]);
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
