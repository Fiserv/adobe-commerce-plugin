<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Controller\Valuelink;

use Fiserv\Payments\Model\Adapter\Valuelink\ValuelinkBalanceRequest;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Webapi\Exception;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class GetValuelinkBalance
 */
class GetValuelinkBalance extends Action implements HttpGetActionInterface
{
	const SESSION_ID_KEY = "sessionId";
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
    * @param Context $context
    * @param CredentialsRequest $chAdapter
    */
    public function __construct(
        Context $context,
        ValuelinkBalanceRequest $chAdapter,
        MultiLevelLogger $logger
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
			$sessionId = $this->getRequest()->getParam(self::SESSION_ID_KEY);
			if(!isset($sessionId))
			{
				throw new InvalidArgumentException("Required Session ID parameter not found.");
			}

			$response->setData(['valuelink_balance' => $this->chAdapter->requestBalance($sessionId)]);
        } catch (\Exception $e) {
			$this->logger->logError(1, "An error occurred while retrieving the Valuelink balance");
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
}
