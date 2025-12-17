<?php
declare(strict_types=1);

namespace Fiserv\Payments\Controller\Subscription;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Customer\Model\Session as CustomerSession;

class Recurring implements HttpGetActionInterface
{
	private PageFactory $resultPageFactory;
	private RedirectFactory $resultRedirectFactory;
	private CustomerSession $customerSession;

	public function __construct(
		PageFactory $resultPageFactory,
		RedirectFactory $resultRedirectFactory,
		CustomerSession $customerSession
	) {
		$this->resultPageFactory = $resultPageFactory;
		$this->resultRedirectFactory = $resultRedirectFactory;
		$this->customerSession = $customerSession;
	}

	public function execute(): ResultInterface
	{
		if (!$this->customerSession->isLoggedIn()) {
			$redirect = $this->resultRedirectFactory->create();
			$redirect->setPath('customer/account/login');
			return $redirect;
		}

		$page = $this->resultPageFactory->create();
		$page->getConfig()->getTitle()->set(__('My Recurring Orders'));
		return $page;
	}
}
