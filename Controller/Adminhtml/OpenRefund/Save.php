<?php

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Fiserv\Payments\Model\OpenRefundFactory;
use Fiserv\Payments\Model\OpenRefund;
use Fiserv\Payments\Model\Service\OpenRefundService;

class Save extends Action implements HttpPostActionInterface
{
    /**
     * @var OpenRefundFactory
     */
    private $openRefundFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var AdminSession
     */
    private $adminSession;

    /**
     * @var OpenRefundService
     */
    private $openRefundService;

    public function __construct(
        Context $context,
        OpenRefundFactory $openRefundFactory,
        CustomerRepositoryInterface $customerRepository,
        AdminSession $adminSession,
        OpenRefundService $openRefundService
    ) {
        parent::__construct($context);
        $this->openRefundFactory = $openRefundFactory;
        $this->customerRepository = $customerRepository;
        $this->adminSession = $adminSession;
        $this->openRefundService = $openRefundService;
    }

    public function execute()
    {
        $postData = $this->getRequest()->getPostValue();
        $data = $postData['data'] ?? $postData;

        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $amount = (float)($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw new LocalizedException(__('Amount must be greater than zero.'));
            }

            $customerId = (int)($data['customer_id'] ?? 0);
            $customerName = '';
            $customerEmail = '';
            if ($customerId) {
                try {
                    $customer = $this->customerRepository->getById($customerId);
                    $customerName = trim($customer->getFirstname() . ' ' . $customer->getLastname());
                    $customerEmail = $customer->getEmail();
                } catch (\Exception $e) {
                    // customer may not exist; proceed without snapshot
                }
            }

            /** @var OpenRefund $openRefund */
            $openRefund = $this->openRefundFactory->create();
            $openRefund->setAmount($amount);
            $openRefund->setCurrencyCode($data['currency_code'] ?? 'USD');
            $openRefund->setCustomerId($customerId ?: null);
            $openRefund->setCustomerName($customerName);
            $openRefund->setCustomerEmail($customerEmail);
            $openRefund->setReferenceTransactionId($data['reference_transaction_id'] ?? null);
            $openRefund->setNotes($data['notes'] ?? null);
            $openRefund->setStatus(OpenRefund::STATUS_PENDING);

            // Admin user ID must always come from the server session — never from a form field
            $adminUser = $this->adminSession->getUser();
            if ($adminUser) {
                $openRefund->setAdminUserId($adminUser->getId());
            }

            $this->openRefundService->submit($openRefund, $data);

            $this->messageManager->addSuccessMessage(__('The open refund was submitted successfully.'));
            return $resultRedirect->setPath('*/*/index');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/edit');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An unexpected error occurred. Please try again.'));
            return $resultRedirect->setPath('*/*/edit');
        }
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Fiserv_Payments::open_refunds_manage');
    }
}

