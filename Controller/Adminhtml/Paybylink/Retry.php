<?php
namespace Fiserv\Payments\Controller\Adminhtml\Paybylink;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Fiserv\Payments\Model\PayByLink\PaymentLinkHandler;

class Retry extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = 'Fiserv_Payments::manage_pay_by_link';

    protected $resultRedirectFactory;
    private $pblHandler;

    public function __construct(
        Context $context,
        RedirectFactory $resultRedirectFactory,
        PaymentLinkHandler $pblHandler
    ) {
        parent::__construct($context);
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->pblHandler = $pblHandler;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('fiserv/paybylink/index');

        $orderId = $this->getRequest()->getParam('order_id');
        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Order id missing.'));
            return $resultRedirect;
        }

        $result = $this->pblHandler->retry($orderId);

        if ($result['success']) {
            $this->messageManager->addSuccessMessage($result['message']);
        } else {
            $this->messageManager->addErrorMessage($result['message']);
        }

        return $resultRedirect;
    }
}
