<?php
declare(strict_types=1);

namespace Fiserv\Payments\Controller\Subscription;

use Fiserv\Payments\Service\SubscriptionDataBuilder;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Frontend AJAX endpoint — returns the logged-in customer's subscription rows as JSON.
 * Implements HttpGetActionInterface so Magento's router allows GET requests through
 * without CSRF validation (GET requests are read-only and safe).
 * Row-building is delegated to SubscriptionDataBuilder; this class only handles
 * customer session auth and scoping (current customer, order-meta fields included).
 */
class RecurringData implements HttpGetActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly SubscriptionDataBuilder $dataBuilder
    ) {}

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['success' => false, 'error' => 'not_logged_in']);
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        $customerEmail = '';
        try {
            $customer = $this->customerSession->getCustomer();
            $customerEmail = $customer ? (string)$customer->getEmail() : '';
        } catch (\Throwable) {}

        // Build a filter that matches by customer_id OR customer_email
        $filters = [];
        if ($customerId > 0 && $customerEmail !== '') {
            $filters = [
                ['field' => ['customer_id', 'customer_email'],
                 'condition' => [['eq' => $customerId], ['eq' => $customerEmail]]],
            ];
        } elseif ($customerId > 0) {
            $filters = [['field' => 'customer_id', 'condition' => $customerId]];
        } else {
            $filters = [['field' => 'customer_email', 'condition' => $customerEmail]];
        }

        $data = $this->dataBuilder->build(
            customerFilter: $filters,
            includeCustomerInfo: false,
            includeOrderMeta: true
        );

        return $result->setData($data);
    }
}

