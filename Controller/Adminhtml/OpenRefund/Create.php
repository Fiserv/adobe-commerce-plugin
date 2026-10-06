<?php

declare(strict_types=1);

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\OpenRefund;
use Fiserv\Payments\Model\Service\CommerceHub\OpenRefundService;
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
            if ($openRefund->getData(OpenRefund::KEY_STATUS) === OpenRefund::STATUS_APPROVED) {
                $this->messageManager->addSuccessMessage(
                    __('Open refund %1 created successfully.', $openRefund->getData(OpenRefund::KEY_OPEN_REFUND_ID))
                );
            } else {
                $errorMessage = trim((string)$openRefund->getData(OpenRefund::KEY_ERROR_MESSAGE));
                $this->messageManager->addErrorMessage(
                    $errorMessage !== '' ? $errorMessage : __('Refund request failed.')
                );
            }
            return $resultRedirect->setPath('fiserv/openrefund/index');
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            /** @var Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('fiserv/openrefund/new');
        }
    }
}

