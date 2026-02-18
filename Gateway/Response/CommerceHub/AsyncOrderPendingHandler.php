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
use Magento\Framework\App\ObjectManager;

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
     * @param SearchCriteriaBuilder|null $searchCriteriaBuilder
     * @param FilterBuilder|null $filterBuilder
     */
    public function __construct(
        SubjectReader $subjectReader,
        OrderRepositoryInterface $orderRepository,
        MultiLevelLogger $logger,
        SearchCriteriaBuilder $searchCriteriaBuilder = null,
        FilterBuilder $filterBuilder = null
    ) {
        $this->subjectReader = $subjectReader;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;

        // Backwards-compatible: builders may not be provided in older DI configurations
        $this->searchCriteriaBuilder = $searchCriteriaBuilder ?: ObjectManager::getInstance()->get(SearchCriteriaBuilder::class);
        $this->filterBuilder = $filterBuilder ?: ObjectManager::getInstance()->get(FilterBuilder::class);
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

        // Diagnostic: log adapter and payment order object type and id availability to help with cross-env issues
        $adapterOrder = $order;
        $paymentOrder = $payment->getOrder();
        try {
            $adapterIdForLog = null;
            if (is_object($adapterOrder) && method_exists($adapterOrder, 'getId')) {
                try {
                    $adapterIdForLog = $adapterOrder->getId();
                } catch (\TypeError $te) {
                    $adapterIdForLog = null;
                }
            }

            $paymentIdForLog = null;
            if (is_object($paymentOrder) && method_exists($paymentOrder, 'getId')) {
                try {
                    $paymentIdForLog = $paymentOrder->getId();
                } catch (\Throwable $te) {
                    $paymentIdForLog = null;
                }
            }
        } catch (\Throwable $e) {
            // ignore logger failures
        }

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
            // Prefer the actual payment order model id if present
            $orderId = null;
            if (is_object($paymentOrder) && method_exists($paymentOrder, 'getId')) {
                try {
                    $paymentOrderId = $paymentOrder->getId();
                } catch (\Throwable $te) {
                    $paymentOrderId = null;
                }
                if ($paymentOrderId) {
                    $orderId = $paymentOrderId;
                }
            }

            if (!$orderId && is_object($adapterOrder) && method_exists($adapterOrder, 'getId')) {
                try {
                    $adapterOrderId = $adapterOrder->getId();
                } catch (\TypeError $te) {
                    $adapterOrderId = null;
                }
                if ($adapterOrderId) {
                    $orderId = $adapterOrderId;
                }
            }

            // If still missing, try loading by increment id via repository search
            if (!$orderId) {
                $incrementId = is_object($paymentOrder) && method_exists($paymentOrder, 'getIncrementId')
                    ? $paymentOrder->getIncrementId()
                    : null;

                if ($incrementId) {
                    try {
                        $filter = $this->filterBuilder->setField('increment_id')->setValue($incrementId)->setConditionType('eq')->create();
                        $searchCriteria = $this->searchCriteriaBuilder->addFilters([$filter])->create();
                        $searchResult = $this->orderRepository->getList($searchCriteria);
                        $items = $searchResult->getItems();
                        if (!empty($items)) {
                            // get first item
                            $orderModel = array_shift($items);
                        } else {
                            return;
                        }
                    } catch (\Throwable $e) {
                        $this->logger->debug('AsyncOrderPendingHandler: repository search error', ['message' => $e->getMessage()]);
                        return;
                    }
                } else {
                    $this->logger->debug('AsyncOrderPendingHandler: missing order id and increment id, skipping update', []);
                    return;
                }
            } else {
                $orderModel = $this->orderRepository->get($orderId);
            }

            $currentState = $orderModel->getState();

            // Only update if order is not in a final state
            if (!in_array($currentState, [Order::STATE_CANCELED, Order::STATE_COMPLETE, Order::STATE_CLOSED], true)) {
                $orderModel->setState(Order::STATE_PENDING_PAYMENT);
                $orderModel->setStatus($orderModel->getConfig()->getStateDefaultStatus(Order::STATE_PENDING_PAYMENT));
                $this->orderRepository->save($orderModel);
            }
        } catch (\Throwable $e) {
            $this->logger->logError(2, "Failed to set order to pending payment: " . $e->getMessage(), ['orderId' => $orderId ?? null, 'adapterOrderId' => $adapterIdForLog, 'paymentOrderId' => $paymentIdForLog]);
        }
    }
}
