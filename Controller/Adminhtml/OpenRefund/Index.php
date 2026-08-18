<?php

declare(strict_types=1);

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\ResourceModel\OpenRefund\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Fiserv_Payments::open_refund_view';
    private const DEFAULT_PAGE_SIZE = 20;
    private const ALLOWED_PAGE_SIZES = [5, 10, 15, 20];
    private const ALLOWED_SORT_COLUMNS = ['created_at', 'amount', 'customer_id'];

    /** @var PageFactory */
    private $pageFactory;

    /** @var JsonFactory */
    private $jsonFactory;

    /** @var CollectionFactory */
    private $collectionFactory;

    /** @var CustomerRepositoryInterface */
    private $customerRepository;

    /** @var array<int, string> */
    private $customerDisplayCache = [];

    public function __construct(
        Action\Context $context,
        PageFactory $pageFactory,
        JsonFactory $jsonFactory,
        CollectionFactory $collectionFactory,
        CustomerRepositoryInterface $customerRepository
    ) {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->jsonFactory = $jsonFactory;
        $this->collectionFactory = $collectionFactory;
        $this->customerRepository = $customerRepository;
    }

    public function execute()
    {
        if ($this->getRequest()->isAjax()) {
            return $this->jsonFactory->create()->setData($this->getPaginatedRows());
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('Fiserv_Payments::open_refunds');
        $resultPage->getConfig()->getTitle()->prepend(__('Open Refunds'));

        return $resultPage;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPaginatedRows(): array
    {
        $page = max(1, (int)$this->getRequest()->getParam('page', 1));
        $pageSize = (int)$this->getRequest()->getParam('pageSize', self::DEFAULT_PAGE_SIZE);
        $searchFilter = trim((string)$this->getRequest()->getParam('searchFilter', ''));
        $statusFilter = trim((string)$this->getRequest()->getParam('statusFilter', ''));
        $sortCol = (string)$this->getRequest()->getParam('sortCol', 'created_at');
        $sortDir = strtolower((string)$this->getRequest()->getParam('sortDir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        if (!in_array($pageSize, self::ALLOWED_PAGE_SIZES, true)) {
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }

        if (!in_array($sortCol, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortCol = 'created_at';
        }

        $collection = $this->collectionFactory->create();

        if ($statusFilter !== '') {
            $collection->addFieldToFilter('status', $statusFilter);
        }

        if ($searchFilter !== '') {
            $conditions = [
                ['like' => '%' . $searchFilter . '%']
            ];
            $fields = ['open_refund_id'];

            if (ctype_digit($searchFilter)) {
                $fields[] = 'customer_id';
                $conditions[] = ['eq' => (int)$searchFilter];
            }

            $collection->addFieldToFilter($fields, $conditions);
        }

        $collection->setOrder($sortCol, $sortDir);
        $collection->setCurPage($page);
        $collection->setPageSize($pageSize);

        $rows = [];
        foreach ($collection->getItems() as $row) {
            $customerDisplay = $this->getCustomerDisplayById((int)$row->getData('customer_id'));
            $status = (string)$row->getData('status');

            $rows[] = [
                'entity_id' => (int)$row->getData('entity_id'),
                'created_at' => (string)$row->getData('created_at'),
                'amount' => number_format((float)$row->getData('amount'), 2),
                'currency_code' => (string)$row->getData('currency_code'),
                'customer_display' => $customerDisplay,
                'open_refund_id' => (string)$row->getData('open_refund_id'),
                'status' => $status,
                'status_class' => 'or-status-' . strtolower($status),
                'view_url' => $this->getUrl('fiserv/openrefund/view', ['id' => (int)$row->getData('entity_id')]),
                'search_data' => strtolower(
                    (string)$row->getData('open_refund_id') . ' ' .
                    (string)$row->getData('customer_id') . ' ' .
                    $customerDisplay
                )
            ];
        }

        $totalRecords = (int)$collection->getSize();

        return [
            'rows' => $rows,
            'totalRecords' => $totalRecords,
            'totalPages' => $totalRecords > 0 ? (int)ceil($totalRecords / $pageSize) : 0,
            'currentPage' => $page
        ];
    }

    private function getCustomerDisplayById(int $customerId): string
    {
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
