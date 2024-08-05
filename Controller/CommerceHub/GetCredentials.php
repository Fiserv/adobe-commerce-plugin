<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Controller\CommerceHub;

use Fiserv\Payments\Model\Adapter\CommerceHub\CredentialsRequest;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Webapi\Exception;
use Psr\Log\LoggerInterface;

/**
 * Class GetChCredentials
 */
class GetCredentials extends Action implements HttpGetActionInterface
{
    const HTTP_UNAUTHORIZED = 401;
    const KEY_STORE_ID = "store_id";

    /**
     * @var CredentialsRequest
     */
    private $chAdapter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
    * @param Context $context
    * @param LoggerInterface $logger
    * @param CredentialsRequest $chAdapter
    */
    public function __construct(
        Context $context,
        CredentialsRequest $chAdapter,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->chAdapter = $chAdapter;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

	try {
	    $response->setData(['ch_credentials' => $this->chAdapter->requestCredentials()]);
        } catch (\Exception $e) {
            $this->logger->critical($e);
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
