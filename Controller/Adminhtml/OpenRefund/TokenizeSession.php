<?php

declare(strict_types=1);

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\Adapter\CommerceHub\TokenizationRequest;
use Fiserv\Payments\Model\Service\CommerceHub\OpenRefundVaultTokenService;
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

    /** @var OpenRefundVaultTokenService */
    private $openRefundVaultTokenService;

    public function __construct(
        Action\Context $context,
        JsonFactory $jsonFactory,
        TokenizationRequest $tokenizationRequest,
        OpenRefundVaultTokenService $openRefundVaultTokenService
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->tokenizationRequest = $tokenizationRequest;
        $this->openRefundVaultTokenService = $openRefundVaultTokenService;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $sessionId = trim((string)$this->getRequest()->getParam('session_id', ''));
        $customerId = (int)$this->getRequest()->getParam('customer_id', 0);

        if ($sessionId === '') {
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
            return $result->setData(['message' => __('Missing payment session id.')]);
        }

        if ($customerId <= 0) {
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
            return $result->setData(['message' => __('Missing customer id.')]);
        }

        try {
            $tokenResponse = $this->tokenizationRequest->tokenizeSession($sessionId);
            $tokenData = (string)($tokenResponse['paymentTokens'][0]['tokenData'] ?? '');
            $tokenSource = (string)($tokenResponse['paymentTokens'][0]['tokenSource'] ?? '');

            if ($tokenData === '' || $tokenSource === '') {
                throw new \InvalidArgumentException('Tokenized card data was not returned by Commerce Hub.');
            }

            $vaultPaymentToken = $this->openRefundVaultTokenService->persistTokenizedCard(
                $tokenResponse,
                $customerId
            );

            $publicHash = (string)$vaultPaymentToken->getPublicHash();

            if ($publicHash === '') {
                throw new \InvalidArgumentException(
                    'Stored payment token public hash was not generated.'
                );
            }

            return $result->setData([
                'token_data' => $tokenData,
                'token_source' => $tokenSource,
                'token_response' => $tokenResponse,
                'public_hash' => $publicHash,
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

