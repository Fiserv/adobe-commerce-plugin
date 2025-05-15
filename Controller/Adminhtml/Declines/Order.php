<?php

namespace Fiserv\Payments\Controller\Adminhtml\Declines;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\PageFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\ResourceModel\FailedOrder as FailedOrderResource;
use Fiserv\Payments\Model\ResourceModel\FailedTransaction as FailedTxnResource;
use Fiserv\Payments\Block\CommerceHub\AdminHtml\Declines\FailedOrders as FailedOrdersBlock;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Fiserv\Payments\Model\Service\CommerceHub\FailedOrderManager;
use Fiserv\Payments\Helper\FailedTransactionHelper;

/**
 * Class FailedOrders
 */
class Order extends Action implements HttpGetActionInterface
{
	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @var PageFactory
	 */
	private $pageFactory;

	/**
	 * @var ForwardFactory
	 */
	private $forwardFactory;

	/**
	 * @var FailedTransactionResource
	 */
	private $orderResourceModel;

	private $txnResourceModel;
	
	private $orderRepo;

	private $search;

	private $failedOrderManager;

	private $failedTransactionHelper;

	/**
	 * @param Context $context
	 * @param MultiLevelLogger $logger
	 * @param PageFactory $pageFactory
	 * @param ForwardFactory $forwardFactory
	 * @param FailedOrderResource $resourceModel
	 */
	public function __construct(
		Context $context,
		MultiLevelLogger $logger,
		PageFactory $pageFactory,
		ForwardFactory $forwardFactory,
		FailedOrderResource $orderResourceModel,
		FailedTxnResource $txnResourceModel,
		OrderRepositoryInterface $orderRepo,
		SearchCriteriaBuilder $search,
		FailedOrderManager $failedOrderManager,
		FailedTransactionHelper $failedTransactionHelper
	) {
		parent::__construct($context);
		$this->logger = $logger;
		$this->pageFactory = $pageFactory;
		$this->forwardFactory = $forwardFactory;
		$this->orderResourceModel = $orderResourceModel;
		$this->txnResourceModel = $txnResourceModel;
		$this->orderRepo = $orderRepo;
		$this->search = $search;
		$this->failedOrderManager = $failedOrderManager;
		$this->failedTransactionHelper = $failedTransactionHelper;
	}

	/**
	 * @inheritdoc
	 */
	public function execute()
	{
		/** @var \Magento\Framework\View\Result\Page $resultPage */
		$resultPage = $this->pageFactory->create();

		try {
			$orderIncrementId = $this->getRequest()->getParam('id');
			if (!isset($orderIncrementId) || empty($orderIncrementId)) {
				throw new \Exception("Order Increment ID not found");
			}

			$failedOrders = $this->orderResourceModel->getByOrderIncrementId($orderIncrementId);
			if (!isset($failedOrders) || empty($failedOrders)) {
					
				// this could be a successful order, so first retrieve successful order
				$failedOrders = $this->getOrderByIncrementId($orderIncrementId);
				if (!isset($failedOrders) || empty($failedOrders))
				{
					throw new \Exception("Failed order(s) for Order Increment ID (" . $orderIncrementId . ") not found.");
				}

				// Successful orders must have failed transactions to be valid for this dashboard
				$failedTxns = $this->txnResourceModel->getByOrderIncrementId($orderIncrementId);
				if (!isset($failedTxns) || empty($failedTxns))
				{
					throw new \Exception("Failed transaction(s) for Order Increment ID (" . $orderIncrementId . ") not found.");
				}

				// create FailedOrder object for dashboard
				$orderId = $failedOrders["entity_id"]; 
				$failedOrder = $this->failedOrderManager->createFailedOrder($failedOrders);
				$failedOrder["real_order_id"] = $orderId;
				$failedOrders = [$failedOrder];
			}
			
			//$resultPage->getLayout()->getBlock('failed_orders')->setData('failed_orders', $failedOrders);

			$order = $this->failedTransactionHelper->getFailedOrder($failedOrders);

			$transactions = $this->failedTransactionHelper->getFailedTransactionsForOrder($failedOrders);

			$giftAmount = $this->failedTransactionHelper->getAppliedGiftCardAmount($failedOrders);

			$resultPage->getLayout()->getBlock('failed_orders')->setData('order', $order);
			$resultPage->getLayout()->getBlock('failed_orders')->setData('transactions', $transactions);
			$resultPage->getLayout()->getBlock('failed_orders')->setData('giftAmount', $giftAmount);

			$resultPage->setActiveMenu("Fiserv_Payments::failed_orders");

			return $resultPage;
		} catch (\Exception $e) {
			$this->logger->logCritical(1, "An error occurred while retrieving failed orders.");
			$this->logger->logCritical(2, $e->getMessage());
			return $this->processBadRequest();
		}
	}

	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Fiserv_Payments::declines');
	}

	private function processBadRequest()
	{
		$resultForward = $this->forwardFactory->create();
		return $resultForward->forward('noroute');
	}

	private function getOrderByIncrementId($incrementId)
	{
		$criteria = $this->search
			->addFilter('increment_id', $incrementId)
			->setPageSize(1)
			->create();

		$orders = $this->orderRepo->getList($criteria)->getItems();
		return $orders[array_key_first($orders)];
	}

}
