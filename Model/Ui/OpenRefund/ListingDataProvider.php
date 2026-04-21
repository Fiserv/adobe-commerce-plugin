<?php

namespace Fiserv\Payments\Model\Ui\OpenRefund;

use Fiserv\Payments\Model\ResourceModel\OpenRefund\CollectionFactory;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

class ListingDataProvider extends DataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly CollectionFactory $collectionFactory,
        \Magento\Framework\Api\Search\ReportingInterface $reporting,
        \Magento\Framework\Api\Search\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $reporting, $searchCriteriaBuilder, $request, $filterBuilder, $meta, $data);
    }

    public function getSearchResult(): SearchResultInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('created_at', \Magento\Framework\Data\Collection::SORT_ORDER_DESC);
        return $collection;
    }

    /**
     * Bypasses parent search-criteria processing to avoid "foreach on null"
     * when SearchCriteria::getFilterGroups() returns null on a fresh criteria object.
     */
    public function getData(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('created_at', \Magento\Framework\Data\Collection::SORT_ORDER_DESC);

        $items = array_map(function ($item) {
            $data = $item->getData();
            if (isset($data['amount'])) {
                $data['amount'] = '$' . number_format((float) $data['amount'], 2);
            }
            return $data;
        }, array_values($collection->getItems()));

        return ['totalRecords' => $collection->getSize(), 'items' => $items];
    }
}

