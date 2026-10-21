<?php
/**
 * Copyright © Fiserv, Inc. All rights reserved.
 */
declare(strict_types=1);

namespace Fiserv\Payments\Gateway\Command\PayByLink;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order;
use Fiserv\Payments\Model\Adapter\CommerceHub\PaymentLinkSessionRequest;
use Fiserv\Payments\Model\PayByLink\PaymentLinkHandler;
use Fiserv\Payments\Model\PayByLink\PaymentLinkErrorResolver;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Pay by Link order command.
 *
 * Runs at order placement time (payment_action = order). It creates the
 * CommerceHub payment-link session and persists the result on the order
 * payment, keeping the order in a pending / new state. Payment itself is
 * collected externally via the generated link, so the order is not authorized
 * or captured here, and no "order" transaction is created.
 *
 * Replaces the legacy {@see \Fiserv\Payments\Gateway\PayByLink\CreatePaymentLinkSession}
 * observer (checkout_submit_all_after) and the InitializeCommand state seeding.
 */
class CreateSessionCommand implements CommandInterface
{
    /**
     * Prefix of the error message shown to the admin when session creation fails.
     */
    public const FAILURE_MESSAGE_PREFIX = 'Pay by Link order failed.';

    /**
     * @var BuilderInterface
     */
    private $requestBuilder;

    /**
     * @var PaymentLinkSessionRequest
     */
    private $sessionRequest;

    /**
     * @var HandlerInterface
     */
    private $handler;

    /**
     * @var PaymentLinkErrorResolver
     */
    private $errorResolver;

    /**
     * @var PaymentLinkHandler
     */
    private $paymentLinkHandler;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
     * @var ChConfig
     */
    private $chConfig;

    /**
     * @param BuilderInterface $requestBuilder
     * @param PaymentLinkSessionRequest $sessionRequest
     * @param HandlerInterface $handler
     * @param PaymentLinkErrorResolver $errorResolver
     * @param PaymentLinkHandler $paymentLinkHandler
     * @param MultiLevelLogger $logger
     * @param ChConfig $chConfig
     */
    public function __construct(
        BuilderInterface $requestBuilder,
        PaymentLinkSessionRequest $sessionRequest,
        HandlerInterface $handler,
        PaymentLinkErrorResolver $errorResolver,
        PaymentLinkHandler $paymentLinkHandler,
        MultiLevelLogger $logger,
        ChConfig $chConfig
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->sessionRequest = $sessionRequest;
        $this->handler = $handler;
        $this->errorResolver = $errorResolver;
        $this->paymentLinkHandler = $paymentLinkHandler;
        $this->logger = $logger;
        $this->chConfig = $chConfig;
    }

    /**
     * @param array $commandSubject
     * @return void
     */
    public function execute(array $commandSubject)
    {
        $paymentDO = $commandSubject['payment'] ?? null;
        if ($paymentDO === null) {
            return;
        }

        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();

        // Keep the order pending / new: skip the order operation's default
        // state transition and the "order" transaction it would otherwise
        // create, since payment is collected later via the generated link.
        $payment->setData('skip_order_processing', true);
        $order->setState(Order::STATE_NEW);
        $order->setStatus('pending');

        try {
            $request = $this->requestBuilder->build($commandSubject);

            $result = $this->sessionRequest->createSession(
                $request['total'],
                $request['currency'],
                $request['expiresInMinutes'],
                $request['pageId'],
                $request['orderData'],
                $request['orderId'],
                $request['paymentAction'],
                $request['storeId'],
                $request['note']
            );

            $this->handler->handle(
                $commandSubject,
                [
                    'paymentAction' => $request['paymentAction'],
                    'expiresInMinutes' => $request['expiresInMinutes'],
                    'pageId' => $request['pageId'],
                    'response' => $result[PaymentLinkSessionRequest::KEY_RESPONSE],
                ]
            );

            $this->logger->logInfo(
                1,
                "Pay by Link session created and persisted successfully",
                "Order: " . $order->getIncrementId()
            );
        } catch (\Throwable $e) {
            $resolvedError = $this->errorResolver->resolve($e);

            $this->logger->logError(1,"Failed to create Pay by Link session",
                json_encode([
                    'timestamp' => gmdate('c'),
                    'order_id' => $order->getIncrementId(),
                    'merchant_id' => (string) $this->chConfig->getMerchantId($order->getStoreId()),
                    'error_code' => $resolvedError['error_code'],
                    'http_status' => $resolvedError['http_status'],
                    'technical_message' => $resolvedError['technical_message'],
                ], JSON_UNESCAPED_SLASHES)


            // Record the failed transaction (failed_transaction row) first, then abort
            // placement: the OrderPlace plugin turns the exception into a failed_order
            // row so the order shows on the Declines dashboard instead of as a real order.
            $this->paymentLinkHandler->applyErrorState($payment, $resolvedError, $e);

            throw new LocalizedException(
                __(
                    '%1 %2 (Reference Code: %3)',
                    self::FAILURE_MESSAGE_PREFIX,
                    $resolvedError['user_message'],
                    $resolvedError['error_code']
                )
            );
        }
    }
}
