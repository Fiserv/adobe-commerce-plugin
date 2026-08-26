<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Controller\Adminhtml\Pbl;

use Fiserv\Payments\Model\Adapter\CommerceHub\TransactionInquiryRequest;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Webapi\Exception;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class TransactionInquiry
 */
class TransactionInquiry extends Action implements HttpGetActionInterface
{
    const HTTP_UNAUTHORIZED = 401;
	const REF_TXN_ID_KEY = "reference_transaction_id";
	
	/**
     * @var TransactionInquiryRequest
     */
    private $chAdapter;

    /**
     * @var MultiLevelLogger
     */
    private $logger;


	/**
    * @param Context $context
    * @param CredentialsRequest $chAdapter
    * @param MultiLevelLogger $logger
    */
    public function __construct(
        Context $context,
        TransactionInquiryRequest $chAdapter,
        MultiLevelLogger $logger,
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
        $this->logger->logInfo(1, "bing bong booodle");
		$response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
			$refTxnId = $this->getRequest()->getParam(self::REF_TXN_ID_KEY);
			if ($refTxnId == null) {
				return $this->processBadRequest($response);
			}

			$inquiryResponse = $this->chAdapter->executeTransactionInquiry($refTxnId);
            $response->setData(['ch_credentials' => $this->chAdapter->requestCredentials($sessionData)]);
        } catch (\Exception $e) {
			$this->logger->logCritical(1, "An error occured during transaction inquiry");
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

	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Fiserv_Payments::transaction_inquiry');
	}
}
