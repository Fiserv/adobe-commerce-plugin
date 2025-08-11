<?php

namespace Fiserv\Payments\Model;

use PayPal\Braintree\Gateway\Data\Order\OrderAdapter;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Sales\Model\Order;
 
class CustomOrderAdapter
{
    protected $logger;
     /**
     * @var Order
     */
    private Order $order;


    public function __construct(
        MultiLevelLogger $logger,
        Order $order
    ) {
        $this->logger = $logger;
        $this->order = $order;
    }

    /**
     * Around plugin for getGrandTotalAmount
     *
     * @param OrderAdapter $subject
     * @param callable $proceed
     * @return float
     */
    public function aroundGetGrandTotalAmount(OrderAdapter $subject, callable $proceed): float
    {
        try {
            $result = $proceed();

            // Ensure the result is a float
            return is_numeric($result) ? (float)$result : 0.0;
        } catch (\Throwable $e) {
            // Only log in development environment
            $orderId = $this->order->getId();
            return floatval($this->order->getBaseGrandTotal()); // Fallback value
                $this->logger->logDebug(3, "Error retrieving order grand total amount in OrderAdapter.", "Order ID: $orderId");
        }
    }
}
 