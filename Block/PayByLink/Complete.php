<?php
namespace Fiserv\Payments\Block\PayByLink;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\OrderRepositoryInterface;

class Complete extends Template
{
    private $searchCriteriaBuilder;
    private $orderRepository;
    private $logger;

    public function __construct(
        Template\Context $context,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepositoryInterface $orderRepository,
        MultiLevelLogger $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    // Resolve the displayed order number to its internal ID for the order view.
    public function getOrderSummaryUrl(string $incrementId): ?string
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $incrementId)
                ->create();
            $orders = $this->orderRepository->getList($searchCriteria)->getItems();
            $order = reset($orders);

            if ($order) {
                return $this->getUrl('sales/order/view', [
                    'order_id' => $order->getEntityId(),
                    '_secure' => true,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->logError(1, 'PBL Complete: Could not build order summary URL: ' . $e->getMessage());
        }

        return null;
    }
}
