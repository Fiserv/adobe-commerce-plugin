<?php

declare(strict_types=1);

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\Service\OpenRefundService;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;

class Create extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Fiserv_Payments::open_refund_create';

    /** @var OpenRefundService */
    private $openRefundService;

    /** @var RedirectFactory */
    private $resultRedirectFactory;

    public function __construct(
        Action\Context $context,
        OpenRefundService $openRefundService,
        RedirectFactory $resultRedirectFactory
    ) {
        parent::__construct($context);
        $this->openRefundService = $openRefundService;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $data = $this->getRequest()->getPostValue();
            $this->openRefundService->create(is_array($data) ? $data : []);
            $this->messageManager->addSuccessMessage(__('Open refund created successfully.'));
            return $resultRedirect->setPath('fiserv/openrefund/index');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('fiserv/openrefund/new');
        }
    }
}

