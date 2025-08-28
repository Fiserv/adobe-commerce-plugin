<?php

namespace Fiserv\Payments\Model;

use PayPal\Braintree\Gateway\Data\Order\OrderAdapter;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
 
class CustomOrderAdapter
{
    protected $logger;
    private OrderRepositoryInterface $orderRepository;

    public function __construct(
        MultiLevelLogger $logger,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Around plugin for getGrandTotalAmount
     *
     * @param OrderAdapter $subject
     * @param callable $proceed
     * @return float
     */
    public function aroundGetGrandTotalAmount(OrderAdapter $subject, callable $proceed):?float
    {
        try {
            $result = $proceed();
                return $result;

        } catch (\TypeError $e) {
            $order = $this->getOrderFromSubject($subject);
            if ($order) {
                $grand = (float)$order->getGrandTotal();
                return $grand;
            }
            throw new \Exception('Unable to retrieve grand total');
        }
    }

    /**
     * Get order from subject (stub, must be implemented as needed)
     *
     * @param OrderAdapter $subject
     * @return Order|null
     */
    private function getOrderFromSubject(OrderAdapter $subject): ?Order
    {
        // Try to get order directly from subject
        if (method_exists($subject, 'getOrder')) {
            $order = $subject->getOrder();
            if ($order instanceof Order) {
                return $order;
            }
        }
        // Try to get order ID from subject and load via repository
        if (method_exists($subject, 'getId')) {
            $orderId = $subject->getId();
            if ($orderId) {
                try {
                    $order = $this->orderRepository->get($orderId);
                    if ($order instanceof Order) {
                        return $order;
                    }
                } catch (\Exception $e) {
                    $this->logger->logDebug(3, 'Error loading order from repository', $orderId);
                }
            }
        }
        return null;
    }
}
