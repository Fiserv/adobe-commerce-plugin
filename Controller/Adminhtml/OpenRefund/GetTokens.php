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

    /** @var JsonFactory */
    private JsonFactory $jsonFactory;

    /** @var PaymentTokenManagementInterface */
    private PaymentTokenManagementInterface $paymentTokenManagement;

    /** @var StoreManagerInterface */
    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        PaymentTokenManagementInterface $paymentTokenManagement,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->jsonFactory            = $jsonFactory;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->storeManager           = $storeManager;
    }

    public function execute()
    {
        $result     = $this->jsonFactory->create();
        $customerId = (int) $this->getRequest()->getParam('customer_id');

        if (!$customerId) {
            return $result->setData(['tokens' => []]);
        }

        // Use the same method as the frontend /vault/cards/listaction page.
        // It applies is_active=1, is_visible=1, expires_at > now(), and website scope.
        // We iterate all stores so admin sees tokens from any website the customer used.
        $seen         = [];
        $tokenOptions = [];

        foreach ($this->storeManager->getStores() as $store) {
            $tokens = $this->paymentTokenManagement->getVisibleAvailableTokens(
                $customerId,
                (int) $store->getId()
            );

            foreach ($tokens as $token) {
                if ($token->getPaymentMethodCode() !== self::PAYMENT_METHOD_CODE) {
                    continue;
                }

                $hash = $token->getPublicHash();
                if (isset($seen[$hash])) {
                    continue; // deduplicate across stores
                }
                $seen[$hash] = true;

                $details = json_decode($token->getTokenDetails() ?: '{}', true);
                $tokenOptions[] = [
                    'value' => $hash,
                    'label' => $this->buildTokenLabel($details),
                ];
            }
        }

        return $result->setData(['tokens' => $tokenOptions]);
    }

    private function buildTokenLabel(array $details): string
    {
        $type  = strtoupper($details['type'] ?? $details['cardType'] ?? 'CARD');
        $last4 = $details['maskedCC'] ?? $details['last4'] ?? '****';
        $exp   = $details['expirationDate'] ?? '';

        if ($exp) {
            return sprintf('%s ending %s (exp %s)', $type, $last4, $exp);
        }

        $expMonth = $details['expMonth'] ?? '';
        $expYear  = $details['expYear'] ?? '';
        if ($expMonth && $expYear) {
            return sprintf('%s ending %s (exp %s/%s)', $type, $last4, $expMonth, $expYear);
        }

        return sprintf('%s ending %s', $type, $last4);
    }

    protected function _isAllowed(): bool
    {
        return true;
    }
}

