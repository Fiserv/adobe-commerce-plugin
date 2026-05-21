<?php
namespace Fiserv\Payments\Helper;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderIdHelper
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Resolve order entity ID from payment order, adapter order, or increment ID lookup.
     *
     * @param mixed $paymentDO
     * @param mixed $payment
     * @return int|null
     */
    public function resolveOrderId($paymentDO, $payment): ?int
    {
        $paymentOrder = $this->safeCall($payment, 'getOrder');
        $adapterOrder = $this->safeCall($paymentDO, 'getOrder');

        $orderId = $this->safeCall($paymentOrder, 'getId') ?: $this->safeCall($adapterOrder, 'getId');
        if ($orderId) {
            return (int)$orderId;
        }

        $incrementId = $this->safeCall($paymentOrder, 'getIncrementId')
            ?: $this->safeCall($adapterOrder, 'getOrderIncrementId')
            ?: $this->safeCall($adapterOrder, 'getIncrementId');

        if (!$incrementId) {
            return null;
        }

        return $this->lookupOrderIdByIncrementId((string)$incrementId);
    }

    /**
     * @param string $incrementId
     * @return int|null
     */
    private function lookupOrderIdByIncrementId(string $incrementId): ?int
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $incrementId)
                ->create();

            $orders = $this->orderRepository->getList($searchCriteria)->getItems();
            if (empty($orders)) {
                return null;
            }

            $orderModel = reset($orders);
            return $orderModel ? (int)$orderModel->getId() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
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