<?php

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Fiserv_Payments::open_refunds');
        $page->getConfig()->getTitle()->prepend(__('Create New Open Refund'));
        return $page;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Fiserv_Payments::open_refunds_manage');
    }
}

