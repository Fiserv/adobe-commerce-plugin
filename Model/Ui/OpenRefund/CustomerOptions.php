<?php

namespace Fiserv\Payments\Model\Ui\OpenRefund;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\OptionSourceInterface;

class CustomerOptions implements OptionSourceInterface
{
    /** @var CustomerRepositoryInterface */
    private CustomerRepositoryInterface $customerRepository;

    /** @var SearchCriteriaBuilder */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /** @var array|null */
    private ?array $options = null;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->customerRepository    = $customerRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $this->options = [['value' => '', 'label' => __('-- Select a Customer --')]];

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $list           = $this->customerRepository->getList($searchCriteria);

        foreach ($list->getItems() as $customer) {
            $name  = trim($customer->getFirstname() . ' ' . $customer->getLastname());
            $email = $customer->getEmail();
            $this->options[] = [
                'value' => $customer->getId(),
                'label' => sprintf('%s <%s>', $name, $email),
            ];
        }

        return $this->options;
    }
}

