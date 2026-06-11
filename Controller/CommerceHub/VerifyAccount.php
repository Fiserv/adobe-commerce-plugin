<?php
/**
 * Copyright  Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Fiserv\Payments\Controller\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Service\CommerceHub\AccountVerificationService;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * AJAX endpoint: POST /fiserv/commercehub/verifyaccount
 * The controller enforces the admin toggle: if account verification is
 * if disabled in the admin panel the request short-circuits with success so that
 * the frontend can proceed to place the order without blocking.
 */
class VerifyAccount extends Action implements HttpPostActionInterface
{
    /** @var Config */
    private $config;

    /** @var AccountVerificationService */
    private $verificationService;

    /** @var MultiLevelLogger */
    private $logger;

    /**
     * @param Context $context
     * @param Config $config
     * @param AccountVerificationService $verificationService
     * @param MultiLevelLogger $logger
     */
    public function __construct(
        Context $context,
        Config $config,
        AccountVerificationService $verificationService,
        MultiLevelLogger $logger
    )
    {
        parent::__construct($context);
        $this->config = $config;
        $this->verificationService = $verificationService;
        $this->logger = $logger;
    }

    /**
     * Handle the AJAX account-verification request.
     *
     * Expected JSON body (session):  { "sessionId": "<payment-session-id>" }
     * Expected JSON body (token):    { "paymentToken": "<token>", "expirationMonth": "MM", "expirationYear": "YYYY" }
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        // If the admin toggle is off, skip verification and let the order proceed.
        if (!$this->config->isAccountVerificationEnabled()) {
            $this->logger->logInfo(2, "VerifyAccount – feature disabled in admin, skipping");
            return $resultJson->setData(["success" => true, "message" => "Verification skipped (disabled)",]);
        }

        // Parse fields from the request body.
        $sessionId = null;
        $paymentToken = null;
        $expirationMonth = null;
        $expirationYear = null;

        $rawBody = $this->getRequest()->getContent();
        if ($rawBody) {
            $data = json_decode($rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (!empty($data["sessionId"])) {
                    $sessionId = trim((string)$data["sessionId"]);
                }
                if (!empty($data["paymentToken"])) {
                    $paymentToken = trim((string)$data["paymentToken"]);
                }
                if (!empty($data["expirationMonth"])) {
                    $expirationMonth = trim((string)$data["expirationMonth"]);
                }
                if (!empty($data["expirationYear"])) {
                    $expirationYear = trim((string)$data["expirationYear"]);
                }
            }
        }

        if (empty($sessionId) && empty($paymentToken)) {
            $this->logger->logError(2, "VerifyAccount – missing sessionId and paymentToken in request body");
            return $resultJson->setHttpResponseCode(400)->
            setData(["success" => false, "message" => "Missing payment session ID or payment token.",]);
        }

        // Delegate to the appropriate service method.
        if (!empty($paymentToken)) {
            $this->logger->logInfo(2, "VerifyAccount – verifying via PaymentToken");
            $result = $this->verificationService->verifyToken($paymentToken, $expirationMonth, $expirationYear);
        } else {
            $result = $this->verificationService->verify($sessionId);
        }

        return $resultJson->setData($result);
    }
}
