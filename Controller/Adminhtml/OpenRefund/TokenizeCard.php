<?php
namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\Adapter\CommerceHub\TokenizationRequest;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class TokenizeCard extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly TokenizationRequest $tokenizationRequest,
        private readonly MultiLevelLogger $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $sessionId = (string) ($this->getRequest()->getParam('session_id') ?? '');

        if ($sessionId === '') {
            return $this->jsonFactory->create()->setData(['error' => true, 'message' => 'session_id is required.']);
        }

        try {
            $this->logger->logInfo(1, '[OpenRefund] Tokenizing PaymentSession for open refund');
            $res = $this->tokenizationRequest->tokenizeSession($sessionId);

            $tokenData = $res['paymentTokens'][0]['tokenData'] ?? '';
            if ($tokenData === '') {
                throw new \RuntimeException('Empty tokenData returned from CommerceHub.');
            }

            $last4 = $res['source']['card']['last4'] ?? '';
            $this->logger->logInfo(2, "[OpenRefund] Tokenization success — last4: {$last4}");

            return $this->jsonFactory->create()->setData([
                'error'       => false,
                'tokenData'   => $tokenData,
                'tokenSource' => $res['paymentTokens'][0]['tokenSource']   ?? '',
                'expMonth'    => $res['source']['card']['expirationMonth'] ?? '',
                'expYear'     => $res['source']['card']['expirationYear']  ?? '',
                'last4'       => $last4,
                'nameOnCard'  => $res['source']['card']['nameOnCard']      ?? '',
            ]);
        } catch (\Exception $e) {
            $this->logger->logError(1, '[OpenRefund] Tokenization failed: ' . $e->getMessage());
            return $this->jsonFactory->create()->setData([
                'error'   => true,
                'message' => 'Card tokenization failed. Please re-enter card details.',
            ]);
        }
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Fiserv_Payments::open_refunds_manage');
    }
}

