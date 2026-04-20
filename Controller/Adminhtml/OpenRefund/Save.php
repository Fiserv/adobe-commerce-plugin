<?php

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Fiserv\Payments\Model\OpenRefundFactory;
use Fiserv\Payments\Model\OpenRefund;
use Fiserv\Payments\Model\Service\OpenRefundService;

class Save extends Action implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonFactory;

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
        JsonFactory $jsonFactory,
        OpenRefundFactory $openRefundFactory,
        CustomerRepositoryInterface $customerRepository,
        AdminSession $adminSession,
        OpenRefundService $openRefundService
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->openRefundFactory = $openRefundFactory;
        $this->customerRepository = $customerRepository;
        $this->adminSession = $adminSession;
        $this->openRefundService = $openRefundService;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $postData = $this->getRequest()->getPostValue();
        $data = $postData['data'] ?? $postData;

        // hosted_field_token is posted at the top level (name="hosted_field_token"),
        // not nested under data[...], so we must merge it in explicitly for the new_card path.
        if (isset($postData['hosted_field_token'])) {
            $data['hosted_field_token'] = $postData['hosted_field_token'];
        }

        try {
            $amount = (float)($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw new LocalizedException(__('Amount must be greater than zero.'));
            }
            // Enforce max 2 decimal places server-side
            if (!preg_match('/^\d+\.\d{1,2}$/', trim((string)($data['amount'] ?? '')))) {
                throw new LocalizedException(__('Amount must be a valid monetary value with 1 or 2 decimal places (e.g. 10.99).'));
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
            $openRefund->setCurrencyCode('USD');
            $openRefund->setCustomerId($customerId ?: null);
            $openRefund->setCustomerName($customerName);
            $openRefund->setCustomerEmail($customerEmail);
            $openRefund->setReferenceTransactionId($data['reference_transaction_id'] ?? null);
            $openRefund->setOrderIncrementId($data['order_increment_id'] ?? null);
            $openRefund->setNotes($data['notes'] ?? null);
            $openRefund->setStatus(OpenRefund::STATUS_PENDING);

            // Admin user ID must always come from the server session — never from a form field
            $adminUser = $this->adminSession->getUser();
            if ($adminUser) {
                $openRefund->setAdminUserId($adminUser->getId());
            }

            $this->openRefundService->submit($openRefund, $data);

            $transactionId = $openRefund->getTransactionId();

            return $result->setData([
                'error'         => false,
                'message'       => (string)__('Open refund submitted successfully.'),
                'transactionId' => $transactionId,
                'entityId'      => $openRefund->getEntityId(),
            ]);
        } catch (LocalizedException $e) {
            return $result->setData([
                'error'   => true,
                'message' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'error'   => true,
                'message' => (string)__('An unexpected error occurred. Please try again.'),
            ]);
        }
    }

    protected function _isAllowed()
    {
        return true;
    }
}

