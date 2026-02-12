<?php
/**
 * Async Order Pending Handler for Affirm
 * Sets order to Pending Payment state and marks payment as pending when receiving PROCESSING/202 response
 */
namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class AsyncOrderPendingHandler implements HandlerInterface
{
    const HTTP_ACCEPTED = 202;
    const STATE_PROCESSING = 'PROCESSING';

    /** @var SubjectReader */
    private $subjectReader;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var MultiLevelLogger */
    private $logger;

    /**
     * @param SubjectReader $subjectReader
     * @param OrderRepositoryInterface $orderRepository
     * @param MultiLevelLogger $logger
     */
    public function __construct(
        SubjectReader $subjectReader,
        OrderRepositoryInterface $orderRepository,
        MultiLevelLogger $logger
    ) {
        $this->subjectReader = $subjectReader;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Mark order and payment as pending for async processing
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();

        $chResponse = $this->subjectReader->readChResponse($response);
        $statusCode = $chResponse[HttpClient::STATUS_CODE_KEY] ?? 200;
        $responseBody = $chResponse[HttpClient::RESPONSE_KEY] ?? [];
        
        $gatewayResponse = $responseBody['gatewayResponse'] ?? [];
        $transactionState = $gatewayResponse['transactionState'] ?? null;
        $transactionProcessingDetails = $gatewayResponse['transactionProcessingDetails'] ?? [];
        $referenceOrderId = $transactionProcessingDetails['orderId'] ?? null;

        $isAsync = ($statusCode == self::HTTP_ACCEPTED) || ($transactionState === self::STATE_PROCESSING);
        if (!$isAsync) {
            return;
        }

        // Mark payment as pending - prevents order from auto-completing
        $payment->setIsTransactionPending(true);
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
        
        // Store async transaction details
        $payment->setAdditionalInformation('async_transaction', true);
        $payment->setAdditionalInformation('transaction_state', self::STATE_PROCESSING);
        if ($referenceOrderId) {
            $payment->setAdditionalInformation('reference_order_id', $referenceOrderId);
        }

        // Set order to Pending Payment state
        try {
            $orderModel = $this->orderRepository->get($order->getId());
            $currentState = $orderModel->getState();
            
            // Only update if order is not in a final state
            if (!in_array($currentState, [Order::STATE_CANCELED, Order::STATE_COMPLETE, Order::STATE_CLOSED], true)) {
                $orderModel->setState(Order::STATE_PENDING_PAYMENT);
                $orderModel->setStatus($orderModel->getConfig()->getStateDefaultStatus(Order::STATE_PENDING_PAYMENT));
                $this->orderRepository->save($orderModel);
            }
        } catch (\Exception $e) {
            // Order may not be saved yet, that's ok
        }
    }
}
