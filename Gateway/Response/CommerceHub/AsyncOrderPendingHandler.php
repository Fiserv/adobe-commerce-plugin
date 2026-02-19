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
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;

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

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var FilterBuilder */
    private $filterBuilder;

    /**
     * @param SubjectReader $subjectReader
     * @param OrderRepositoryInterface $orderRepository
     * @param MultiLevelLogger $logger
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     */
    public function __construct(
        SubjectReader $subjectReader,
        OrderRepositoryInterface $orderRepository,
        MultiLevelLogger $logger,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder
    ) {
        $this->subjectReader = $subjectReader;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
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
        $this->updateOrderToPendingPayment($paymentDO, $payment);
    }

    /**
     * Update order to pending payment state
     *
     * @param \Magento\Payment\Gateway\Data\PaymentDataObjectInterface $paymentDO
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $payment
     * @return void
     */
    private function updateOrderToPendingPayment($paymentDO, $payment): void
    {
        try {
            $orderModel = $this->resolveOrderModel($paymentDO, $payment);
            
            if (!$orderModel) {
                return;
            }

            $currentState = $orderModel->getState();

            // Only update if order is not in a final state
            if (!in_array($currentState, [Order::STATE_CANCELED, Order::STATE_COMPLETE, Order::STATE_CLOSED], true)) {
                $orderModel->setState(Order::STATE_PENDING_PAYMENT);
                $orderModel->setStatus($orderModel->getConfig()->getStateDefaultStatus(Order::STATE_PENDING_PAYMENT));
                $this->orderRepository->save($orderModel);
            }
        } catch (\Throwable $e) {
            $this->logger->debug('AsyncOrderPendingHandler: failed to update order state', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Resolve order model from payment data object or payment order
     *
     * @param \Magento\Payment\Gateway\Data\PaymentDataObjectInterface $paymentDO
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $payment
     * @return \Magento\Sales\Model\Order|null
     */
    private function resolveOrderModel($paymentDO, $payment): ?Order
    {
        $adapterOrder = $paymentDO->getOrder();
        $paymentOrder = $payment->getOrder();

        // Try to get order ID from payment order or adapter
        $orderId = $this->safeCall($paymentOrder, 'getId') ?: $this->safeCall($adapterOrder, 'getId');

        if ($orderId) {
            return $this->orderRepository->get($orderId);
        }

        // Fallback: lookup by increment ID
        $incrementId = $this->safeCall($paymentOrder, 'getIncrementId');
        if ($incrementId) {
            return $this->lookupOrderByIncrementId($incrementId);
        }

        return null;
    }

    /**
     * Lookup order by increment ID via repository
     *
     * @param string $incrementId
     * @return \Magento\Sales\Model\Order|null
     */
    private function lookupOrderByIncrementId(string $incrementId): ?Order
    {
        try {
            $filter = $this->filterBuilder
                ->setField('increment_id')
                ->setValue($incrementId)
                ->setConditionType('eq')
                ->create();
            
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilters([$filter])
                ->create();
            
            $searchResult = $this->orderRepository->getList($searchCriteria);
            $items = $searchResult->getItems();
            
            return !empty($items) ? array_shift($items) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Safely call a method on an object, catching TypeError and other exceptions
     *
     * @param mixed $object
     * @param string $method
     * @return mixed|null
     */
    private function safeCall($object, string $method)
    {
        if (!is_object($object) || !method_exists($object, $method)) {
            return null;
        }

        try {
            return $object->$method();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
