<?php

declare(strict_types=1);

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\Service\OpenRefundService;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Create extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Fiserv_Payments::open_refund_create';

    /** @var OpenRefundService */
    private $openRefundService;

    public function __construct(
        Action\Context $context,
        OpenRefundService $openRefundService
    ) {
        parent::__construct($context);
        $this->openRefundService     = $openRefundService;
    }

    public function execute()
    {
        try {
            /** @var Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();

            $data = $this->getRequest()->getPostValue();
            $openRefund = $this->openRefundService->create(is_array($data) ? $data : []);
            $this->messageManager->addSuccessMessage(
                __('Open refund %1 created successfully.', $openRefund->getData('open_refund_id'))
            );
            return $resultRedirect->setPath('fiserv/openrefund/index');
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            /** @var Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('fiserv/openrefund/new');
        }
    }
}

