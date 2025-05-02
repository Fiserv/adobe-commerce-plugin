<?php
namespace Fiserv\Payments\Controller\Adminhtml\Declines;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Fiserv\Payments\Helper\DeclinedOrdersHelper;

use Fiserv\Payments\Model\FailedOrder as OrderModel;

class Preview extends Action implements HttpGetActionInterface
{
	private $logger;
	private $jsonFactory;
	private $pageFactory;

	public function __construct(
		Context $context,
		MultiLevelLogger $logger,
		JsonFactory $jsonFactory,
		PageFactory $pageFactory
	) {
		parent::__construct($context);
		$this->logger = $logger;
		$this->jsonFactory = $jsonFactory;
		$this->pageFactory = $pageFactory;
	}

	public function execute()
	{
		if ($this->getRequest()->isAjax()) {
			$page = (int) $this->getRequest()->getParam('page', 1);
			$pageSize = (int) $this->getRequest()->getParam('pageSize', 5);
			$search = $this->getRequest()->getParam('searchFilter', '');

			$orders = DeclinedOrdersHelper::getOrdersWithDeclines($page, $pageSize, $search);

			$result = $this->jsonFactory->create();
			return $result->setData($orders);
		}

		$resultPage = $this->pageFactory->create();
		$resultPage->setActiveMenu("Fiserv_Payments::failed_transaction_preview");
		$resultPage->getConfig()->getTitle()->prepend(__('UNSUCCESSFUL ORDERS'));

		$orders = DeclinedOrdersHelper::getOrdersWithDeclines(1, 5);
		$resultPage->getLayout()->getBlock('failed_transaction_preview')->setData('orders', $orders['orders']);
		$resultPage->getLayout()->getBlock('failed_transaction_preview')->setData('totalPages', $orders['totalPages']);

		return $resultPage;
	}
}