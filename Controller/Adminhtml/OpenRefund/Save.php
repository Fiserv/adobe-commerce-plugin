<?php

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\OpenRefundFactory;
use Fiserv\Payments\Model\OpenRefund;
use Fiserv\Payments\Model\Service\OpenRefund\OpenRefundService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly OpenRefundFactory $openRefundFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AdminSession $adminSession,
        private readonly OpenRefundService $openRefundService
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result   = $this->jsonFactory->create();
        $postData = $this->getRequest()->getPostValue();
        $data     = $postData['data'] ?? $postData;

        foreach (['payment_source_type', 'new_card_token_data', 'new_card_token_source',
                  'new_card_exp_month', 'new_card_exp_year', 'new_card_name_on_card'] as $key) {
            if (isset($postData[$key]) && !isset($data[$key])) {
                $data[$key] = $postData[$key];
            }
        }

        try {
            $amount = (float) ($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw new LocalizedException(__('Amount must be greater than zero.'));
            }
            if (!preg_match('/^\d+\.\d{1,2}$/', trim((string) ($data['amount'] ?? '')))) {
                throw new LocalizedException(__('Amount must be a valid monetary value with 1 or 2 decimal places (e.g. 10.99).'));
            }

            $customerId   = (int) ($data['customer_id'] ?? 0);
            $customerName = $customerEmail = '';
            if ($customerId) {
                try {
                    $customer     = $this->customerRepository->getById($customerId);
                    $customerName = trim($customer->getFirstname() . ' ' . $customer->getLastname());
                    $customerEmail = $customer->getEmail();
                } catch (\Exception $e) { // customer not found — proceed without snapshot
                }
            }

            /** @var OpenRefund $openRefund */
            $openRefund = $this->openRefundFactory->create();
            $openRefund->setAmount($amount);
            $openRefund->setCurrencyCode('USD');
            $openRefund->setCustomerId($customerId ?: null);
            $openRefund->setCustomerName($customerName);
            $openRefund->setCustomerEmail($customerEmail);
            $openRefund->setNotes($data['notes'] ?? null);
            $openRefund->setStatus(OpenRefund::STATUS_PENDING);

            if ($adminUser = $this->adminSession->getUser()) {
                $openRefund->setAdminUserId($adminUser->getId());
            }

            $this->openRefundService->submit($openRefund, $data);

            return $result->setData([
                'error'         => false,
                'message'       => (string) __('Open refund submitted successfully.'),
                'transactionId' => $openRefund->getTransactionId(),
                'entityId'      => $openRefund->getEntityId(),
            ]);
        } catch (LocalizedException $e) {
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return $result->setData(['error' => true, 'message' => (string) __('An unexpected error occurred. Please try again.')]);
        }
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Fiserv_Payments::open_refunds_manage');
    }
}

