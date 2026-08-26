<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Controller\Adminhtml\Pbl;

use Fiserv\Payments\Model\Adapter\CommerceHub\PaymentLinkCancelRequest;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Webapi\Exception;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class CancelPaymentLink
 */
class CancelPaymentLink extends Action implements HttpPostActionInterface
{
    const HTTP_UNAUTHORIZED = 401;
	const MERCHANT_ID_KEY = "merchant_id";
	const TERMINAL_ID_KEY = "terminal_id";
	const REFERENCE_TRANSACTION_ID_KEY = "reference_transaction_id";

	/**
     * @var PaymentLinkCancelRequest
     */
    private $chAdapter;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

	/**
    * @param Context $context
    * @param PaymentLinkCancelRequest $chAdapter
    * @param MultiLevelLogger $logger
    */
    public function __construct(
        Context $context,
        PaymentLinkCancelRequest $chAdapter,
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
        $this->logger->logInfo(1, "Payment link cancellation initiated");
		$response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
			$merchantId = $this->getRequest()->getParam(self::MERCHANT_ID_KEY);
			$terminalId = $this->getRequest()->getParam(self::TERMINAL_ID_KEY);
			$referenceTransactionId = $this->getRequest()->getParam(self::REFERENCE_TRANSACTION_ID_KEY);

			if (!$merchantId || !$terminalId || !$referenceTransactionId) {
				return $this->processBadRequest($response);
			}

			$cancelResponse = $this->chAdapter->cancelPaymentLink($merchantId, $terminalId, $referenceTransactionId);
            $response->setData(['message' => 'Payment link has been cancelled successfully']);
        } catch (\Exception $e) {
			$this->logger->logCritical(1, "An error occurred during payment link cancellation");
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
		return $this->_authorization->isAllowed('Fiserv_Payments::pbl');
	}
}
