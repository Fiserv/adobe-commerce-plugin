<?php

declare(strict_types=1);

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * AJAX endpoint — returns active vault tokens for a given customer.
 * GET  fiserv/openrefund/getcustomertokens?customer_id=<id>
 */
class GetCustomerTokens extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Fiserv_Payments::open_refund_create';

    /** @var JsonFactory */
    private $jsonFactory;

    /** @var PaymentTokenManagementInterface */
    private $tokenManagement;

    /** @var DateTimeFactory */
    private $dateTimeFactory;

    public function __construct(
        Action\Context $context,
        JsonFactory $jsonFactory,
        PaymentTokenManagementInterface $tokenManagement,
        DateTimeFactory $dateTimeFactory
    ) {
        parent::__construct($context);
        $this->jsonFactory     = $jsonFactory;
        $this->tokenManagement = $tokenManagement;
        $this->dateTimeFactory = $dateTimeFactory;
    }

    public function execute()
    {
        $result     = $this->jsonFactory->create();
        $customerId = (int)$this->getRequest()->getParam('customer_id', 0);

        if ($customerId <= 0) {
            return $result->setData([]);
        }

        try {
            $now    = $this->dateTimeFactory->create('now', new \DateTimeZone('UTC'));
            $tokens = $this->tokenManagement->getListByCustomerId($customerId);

            $items = [];
            foreach ($tokens as $token) {
                if (!$this->isUsable($token, $now)) {
                    continue;
                }

                $details = json_decode($token->getTokenDetails() ?? '{}', true);

                $items[] = [
                    'public_hash'     => $token->getPublicHash(),
                    'type'            => $details['type'] ?? '',
                    'masked_cc'       => $details['maskedCC'] ?? '',
                    'expiration_date' => $details['expirationDate'] ?? '',
                    'label'           => $this->buildLabel($details),
                ];
            }

            return $result->setData($items);
        } catch (\Exception $e) {
            return $result->setData(['error' => __('Unable to retrieve stored cards.')]);
        }
    }

    private function isUsable(PaymentTokenInterface $token, \DateTime $now): bool
    {
        if (!$token->getIsActive() || !$token->getIsVisible()) {
            return false;
        }
        if ($token->getPaymentMethodCode() !== ConfigProvider::CODE) {
            return false;
        }
        $expiresAt = $token->getExpiresAt();
        if ($expiresAt && strtotime($expiresAt) <= $now->getTimestamp()) {
            return false;
        }
        return true;
    }

    private function buildLabel(array $details): string
    {
        $type    = strtoupper($details['type'] ?? '');
        $masked  = $details['maskedCC'] ?? '';
        $expiry  = $details['expirationDate'] ?? '';

        $label = $type ? $type . ' ' : '';
        $label .= $masked ? 'ending ' . $masked : '';
        $label .= $expiry ? ' (exp. ' . $expiry . ')' : '';

        return trim($label) ?: __('Stored card');
    }
}

