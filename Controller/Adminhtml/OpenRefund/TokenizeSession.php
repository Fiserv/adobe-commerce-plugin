<?php

declare(strict_types=1);

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\Adapter\CommerceHub\TokenizationRequest;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception;

class TokenizeSession extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Fiserv_Payments::open_refund_create';

    /** @var JsonFactory */
    private $jsonFactory;

    /** @var TokenizationRequest */
    private $tokenizationRequest;

    public function __construct(
        Action\Context $context,
        JsonFactory $jsonFactory,
        TokenizationRequest $tokenizationRequest
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->tokenizationRequest = $tokenizationRequest;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $sessionId = trim((string)$this->getRequest()->getParam('session_id', ''));

        if ($sessionId === '') {
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
            return $result->setData(['message' => __('Missing payment session id.')]);
        }

        try {
            $tokenResponse = $this->tokenizationRequest->tokenizeSession($sessionId);
            $tokenData = (string)($tokenResponse['paymentTokens'][0]['tokenData'] ?? '');

            if ($tokenData === '') {
                throw new \InvalidArgumentException('Tokenized card data was not returned by Commerce Hub.');
            }

            return $result->setData([
                'token_data' => $tokenData,
                'token_response' => $tokenResponse,
            ]);
        } catch (\InvalidArgumentException $e) {
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
            return $result->setData(['message' => __($e->getMessage())]);
        } catch (\Throwable $e) {
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
            return $result->setData(['message' => __('Unable to tokenize the payment session. Please retry.')]);
        }
    }
}

