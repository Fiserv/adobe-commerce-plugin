<?php
declare(strict_types=1);

namespace Fiserv\Payments\Controller\Adminhtml\Subscription;

use Fiserv\Payments\Service\SubscriptionDataBuilder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Admin AJAX endpoint — returns ALL subscription rows as JSON.
 * Row-building is delegated to SubscriptionDataBuilder; this class only
 * handles admin auth and scoping (all customers, customer-info fields included).
 */
class RecurringData extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly SubscriptionDataBuilder $dataBuilder
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $data = $this->dataBuilder->build(
            customerFilter:      null,   // all customers
            includeCustomerInfo: true,
            includeOrderMeta:    false
        );
        return $result->setData($data);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Fiserv_Payments::recurring_orders');
    }
}

