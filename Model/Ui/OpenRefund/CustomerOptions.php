<?php

namespace Fiserv\Payments\Model\Ui\OpenRefund;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\OptionSourceInterface;

class CustomerOptions implements OptionSourceInterface
{
    private ?array $options = null;

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {}

    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $this->options = [['value' => '', 'label' => __('-- Select a Customer --')]];

        foreach ($this->customerRepository->getList($this->searchCriteriaBuilder->create())->getItems() as $customer) {
            $this->options[] = [
                'value' => $customer->getId(),
                'label' => trim($customer->getFirstname() . ' ' . $customer->getLastname()) . ' <' . $customer->getEmail() . '>',
            ];
        }

        return $this->options;
    }
}
