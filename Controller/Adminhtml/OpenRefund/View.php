<?php

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\OpenRefundFactory;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class View extends Action implements HttpGetActionInterface
{
	public const ADMIN_RESOURCE = 'Fiserv_Payments::open_refund_view';

	private $pageFactory;

	private $openRefundFactory;

	private $registry;

	public function __construct(
		Action\Context $context,
		PageFactory $pageFactory,
		OpenRefundFactory $openRefundFactory,
		Registry $registry
	) {
		parent::__construct($context);
		$this->pageFactory = $pageFactory;
		$this->openRefundFactory = $openRefundFactory;
		$this->registry = $registry;
	}

	public function execute()
	{
		$entityId = (int)$this->getRequest()->getParam('id');
		$openRefund = $this->openRefundFactory->create()->load($entityId);

		if (!$entityId || !$openRefund->getId()) {
			$this->messageManager->addErrorMessage(__('The requested open refund no longer exists.'));
			return $this->resultRedirectFactory->create()->setPath('fiserv/openrefund/index');
		}

		$this->registry->register('fiserv_open_refund_current', $openRefund);

		$resultPage = $this->pageFactory->create();
		$resultPage->setActiveMenu('Fiserv_Payments::open_refunds');
		$resultPage->getConfig()->getTitle()->prepend(__('Open Refunds'));
		$resultPage->getConfig()->getTitle()->prepend(__('View Open Refund %1', (string)$openRefund->getData('open_refund_id')));

		return $resultPage;
	}
}
