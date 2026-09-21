<?php
/**
 * Copyright © Fiserv, Inc. All rights reserved.
 */
declare(strict_types=1);

namespace Fiserv\Payments\Gateway\Response\PayByLink;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Fiserv\Payments\Model\PayByLink\PaymentLinkHandler;

/**
 * Persists the Pay by Link session-create response onto the order payment.
 *
 * The handling subject carries the payment data object; the response array is
 * the structure assembled by the order command (request context merged with the
 * raw CommerceHub session body).
 */
class SessionHandler implements HandlerInterface
{
    /**
     * @var PaymentLinkHandler
     */
    private $paymentLinkHandler;

    /**
     * @param PaymentLinkHandler $paymentLinkHandler
     */
    public function __construct(PaymentLinkHandler $paymentLinkHandler)
    {
        $this->paymentLinkHandler = $paymentLinkHandler;
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = $handlingSubject['payment'] ?? null;
        if ($paymentDO === null) {
            return;
        }

        $payment = $paymentDO->getPayment();

        $paymentAction = (string) ($response['paymentAction'] ?? '');
        $requestExpiresInMinutes = (int) ($response['expiresInMinutes'] ?? 0);
        $requestPageId = $response['pageId'] ?? null;
        $body = is_array($response['response'] ?? null) ? $response['response'] : [];

        $payByLinkUrl = $body['checkoutInteractions']['url'] ?? '';
        $sessionId = $body['session']['sessionId'] ?? '';
        $transactionId = $body['gatewayResponse']['transactionProcessingDetails']['transactionId'] ?? '';
        $apiTraceId = $body['gatewayResponse']['transactionProcessingDetails']['apiTraceId'] ?? '';
        $responseExpiresInMinutes = $body['checkoutInteractions']['expiresInMinutes'] ?? $requestExpiresInMinutes;
        $responsePageId = $body['checkoutInteractions']['pageId'] ?? $requestPageId;

        // Persist common PBL data on payment additional_information
        $this->paymentLinkHandler->applySuccessState(
            $payment,
            $payByLinkUrl,
            $sessionId,
            $transactionId,
            $paymentAction,
            (int) $responseExpiresInMinutes,
            $response['returnTokenHash'] ?? ''
        );

        // Channel-specific fields captured during initial session create
        $payment->setAdditionalInformation('pbl_api_trace_id', $apiTraceId);
        $payment->setAdditionalInformation('pbl_page_id', $responsePageId);
    }
}
