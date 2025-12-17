<?php
namespace Fiserv\Payments\Controller\Adminhtml\Subscription;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Preview extends Action implements HttpGetActionInterface
{
	private PageFactory $pageFactory;

	public function __construct(Context $context, PageFactory $pageFactory)
	{
		parent::__construct($context);
		$this->pageFactory = $pageFactory;
	}

	public function execute()
	{
		$resultPage = $this->pageFactory->create();
		$resultPage->setActiveMenu('Fiserv_Payments::recurring_orders');
		$resultPage->getConfig()->getTitle()->prepend(__('Recurring Orders'));
		return $resultPage;
	}

	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Fiserv_Payments::recurring_orders');
	}
}
