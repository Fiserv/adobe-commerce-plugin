<?php

declare(strict_types=1);

namespace Fiserv\Payments\Block\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\ResourceModel\OpenRefund\CollectionFactory;
use Magento\Backend\Block\Template;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Dashboard extends Template
{
    private const DEFAULT_PAGE_SIZE = 20;
    private const ALLOWED_PAGE_SIZES = [5, 10, 15, 20];

    /** @var CollectionFactory */
    private $collectionFactory;

    /** @var CustomerRepositoryInterface */
    private $customerRepository;

    /** @var array<int, string> */
    private $customerDisplayCache = [];

    /** @var \Fiserv\Payments\Model\ResourceModel\OpenRefund\Collection|null */
    private $rowsCollection;

    public function __construct(
        Template\Context $context,
        CollectionFactory $collectionFactory,
        CustomerRepositoryInterface $customerRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
        $this->customerRepository = $customerRepository;
    }

    public function getCreateUrl(): string
    {
        return $this->getUrl('fiserv/openrefund/new');
    }

    public function getAjaxUrl(): string
    {
        return $this->getUrl('fiserv/openrefund/index');
    }

    public function getViewUrl(int $entityId): string
    {
	    return $this->getUrl('fiserv/openrefund/view', ['id' => $entityId]);
    }

    /**
     * Returns open refund records sorted by recency (most recent first).
     *
     * @return \Fiserv\Payments\Model\OpenRefund[]
     */
    public function getRows(): array
    {
        return array_values($this->getRowsCollection()->getItems());
    }

    public function getCurrentPage(): int
    {
        return max(1, (int)$this->getRequest()->getParam('p', 1));
    }

    public function getPageSize(): int
    {
        $pageSize = (int)$this->getRequest()->getParam('limit', self::DEFAULT_PAGE_SIZE);

        if (!in_array($pageSize, self::ALLOWED_PAGE_SIZES, true)) {
            return self::DEFAULT_PAGE_SIZE;
        }

        return $pageSize;
    }

    /**
     * @return int[]
     */
    public function getAllowedPageSizes(): array
    {
        return self::ALLOWED_PAGE_SIZES;
    }

    public function getTotalPages(): int
    {
        $pageSize = $this->getPageSize();
        $totalRecords = (int)$this->getRowsCollection()->getSize();

        if ($totalRecords <= 0) {
            return 0;
        }

        return (int)ceil($totalRecords / $pageSize);
    }

    private function getRowsCollection(): \Fiserv\Payments\Model\ResourceModel\OpenRefund\Collection
    {
        if ($this->rowsCollection !== null) {
            return $this->rowsCollection;
        }

        $collection = $this->collectionFactory->create();
        $collection->setOrder('created_at', 'DESC');

        $collection->setCurPage($this->getCurrentPage());
        $collection->setPageSize($this->getPageSize());
        $this->rowsCollection = $collection;

        return $this->rowsCollection;
    }

    public function getCustomerDisplayById($customerId): string
    {
        $customerId = (int)$customerId;
        if ($customerId <= 0) {
            return 'Guest';
        }

        if (isset($this->customerDisplayCache[$customerId])) {
            return $this->customerDisplayCache[$customerId];
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $name = trim((string)$customer->getFirstname() . ' ' . (string)$customer->getLastname());
            $email = (string)$customer->getEmail();

            if ($name !== '' && $email !== '') {
                $this->customerDisplayCache[$customerId] = $name . ' <' . $email . '>';
                return $this->customerDisplayCache[$customerId];
            }

            if ($email !== '') {
                $this->customerDisplayCache[$customerId] = $email;
                return $this->customerDisplayCache[$customerId];
            }

            $this->customerDisplayCache[$customerId] = (string)$customerId;
            return $this->customerDisplayCache[$customerId];
        } catch (NoSuchEntityException $e) {
            $this->customerDisplayCache[$customerId] = 'Customer not found (ID: ' . $customerId . ')';
            return $this->customerDisplayCache[$customerId];
        }
    }
}
