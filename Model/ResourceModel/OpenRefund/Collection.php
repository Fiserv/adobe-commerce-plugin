<?php

namespace Fiserv\Payments\Model\ResourceModel\OpenRefund;

use Fiserv\Payments\Model\OpenRefund;
use Fiserv\Payments\Model\ResourceModel\OpenRefund as OpenRefundResource;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection implements SearchResultInterface
{
    protected $_idFieldName = 'entity_id';

    /** @var AggregationInterface */
    private AggregationInterface $aggregations;

    protected function _construct()
    {
        $this->_init(OpenRefund::class, OpenRefundResource::class);
    }

    public function getAggregations(): AggregationInterface
    {
        return $this->aggregations;
    }

    public function setAggregations($aggregations): self
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    public function getSearchCriteria(): ?SearchCriteriaInterface
    {
        return null;
    }

    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria): self
    {
        return $this;
    }

    public function getTotalCount(): int
    {
        return $this->getSize();
    }

    public function setTotalCount($totalCount): self
    {
        return $this;
    }

    public function setItems(array $items = null): self
    {
        return $this;
    }
}

