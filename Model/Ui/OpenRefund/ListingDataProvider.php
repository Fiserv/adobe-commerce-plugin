<?php

namespace Fiserv\Payments\Model\Ui\OpenRefund;

use Fiserv\Payments\Model\ResourceModel\OpenRefund\Collection;
use Fiserv\Payments\Model\ResourceModel\OpenRefund\CollectionFactory;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

class ListingDataProvider extends DataProvider
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        \Magento\Framework\Api\Search\ReportingInterface $reporting,
        \Magento\Framework\Api\Search\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        array $meta = [],
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
    }

    public function getSearchResult(): SearchResultInterface
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->setOrder('created_at', \Magento\Framework\Data\Collection::SORT_ORDER_DESC);
        return $collection;
    }
}

