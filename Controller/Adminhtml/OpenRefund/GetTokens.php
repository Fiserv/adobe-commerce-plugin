<?php

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;

class GetTokens extends Action implements HttpGetActionInterface
{
    const PAYMENT_METHOD_CODE = 'fiserv_commercehub';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly PaymentTokenManagementInterface $paymentTokenManagement,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $customerId = (int) $this->getRequest()->getParam('customer_id');

        if (!$customerId) {
            return $this->jsonFactory->create()->setData(['tokens' => []]);
        }

        $seen    = [];
        $options = [];

        foreach ($this->storeManager->getStores() as $store) {
            foreach ($this->paymentTokenManagement->getVisibleAvailableTokens($customerId, (int) $store->getId()) as $token) {
                if ($token->getPaymentMethodCode() !== self::PAYMENT_METHOD_CODE) {
                    continue;
                }
                $hash = $token->getPublicHash();
                if (isset($seen[$hash])) {
                    continue;
                }
                $seen[$hash] = true;
                $details     = json_decode($token->getTokenDetails() ?: '{}', true);
                $options[]   = ['value' => $hash, 'label' => $this->buildLabel($details)];
            }
        }

        return $this->jsonFactory->create()->setData(['tokens' => $options]);
    }

    private function buildLabel(array $d): string
    {
        $type  = strtoupper($d['type'] ?? $d['cardType'] ?? 'CARD');
        $last4 = $d['maskedCC'] ?? $d['last4'] ?? '****';
        $exp   = $d['expirationDate'] ?? '';

        if ($exp) {
            return "{$type} ending {$last4} (exp {$exp})";
        }
        if (($d['expMonth'] ?? '') && ($d['expYear'] ?? '')) {
            return "{$type} ending {$last4} (exp {$d['expMonth']}/{$d['expYear']})";
        }
        return "{$type} ending {$last4}";
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Fiserv_Payments::open_refunds_manage');
    }
}

