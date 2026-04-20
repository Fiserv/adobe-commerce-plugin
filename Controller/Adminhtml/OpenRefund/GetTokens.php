<?php

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class GetTokens extends Action implements HttpGetActionInterface
{
    const PAYMENT_METHOD_CODE = 'fiserv_commercehub';

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $customerId = (int)$this->getRequest()->getParam('customer_id');

        if (!$customerId) {
            return $result->setData(['tokens' => []]);
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('customer_id', $customerId)
            ->addFilter('is_active', 1)
            ->addFilter('payment_method_code', self::PAYMENT_METHOD_CODE)
            ->create();

        $tokens = $this->paymentTokenRepository->getList($searchCriteria)->getItems();

        $tokenOptions = [];
        foreach ($tokens as $token) {
            $details = json_decode($token->getTokenDetails() ?: '{}', true);
            $label = $this->buildTokenLabel($details);
            $tokenOptions[] = [
                'value' => $token->getPublicHash(),
                'label' => $label,
            ];
        }

        return $result->setData(['tokens' => $tokenOptions]);
    }

    private function buildTokenLabel(array $details): string
    {
        $type = strtoupper($details['type'] ?? $details['cardType'] ?? 'CARD');
        $last4 = $details['maskedCC'] ?? $details['last4'] ?? '****';
        $exp = $details['expirationDate'] ?? '';

        // expirationDate is typically stored as "MM/YY" or "MM/YYYY"
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

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Fiserv_Payments::open_refunds_manage');
    }
}

