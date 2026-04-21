<?php

namespace Fiserv\Payments\Model;

use Fiserv\Payments\Api\Data\OpenRefund\OpenRefundInterface;
use Fiserv\Payments\Api\Data\OpenRefund\OpenRefundSearchResultInterface;
use Fiserv\Payments\Api\OpenRefund\OpenRefundRepositoryInterface;
use Fiserv\Payments\Model\ResourceModel\OpenRefund as OpenRefundResource;
use Fiserv\Payments\Model\ResourceModel\OpenRefund\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class OpenRefundRepository implements OpenRefundRepositoryInterface
{
    public function __construct(
        private readonly OpenRefundResource $resource,
        private readonly OpenRefundFactory $openRefundFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly OpenRefund\OpenRefundSearchResultFactory $searchResultFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {}

    public function get(int $entityId): OpenRefundInterface
    {
        $openRefund = $this->openRefundFactory->create();
        $this->resource->load($openRefund, $entityId);

        if (!$openRefund->getId()) {
            throw new NoSuchEntityException(__('Open Refund with ID "%1" does not exist.', $entityId));
        }

        return $openRefund;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): OpenRefundSearchResultInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $result = $this->searchResultFactory->create();
        $result->setSearchCriteria($searchCriteria);
        $result->setItems($collection->getItems());
        $result->setTotalCount($collection->getSize());

        return $result;
    }

    public function save(OpenRefundInterface $openRefund): OpenRefundInterface
    {
        try {
            $this->resource->save($openRefund);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save Open Refund: %1', $e->getMessage()));
        }
        return $openRefund;
    }

    public function delete(OpenRefundInterface $openRefund): bool
    {
        try {
            $this->resource->delete($openRefund);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete Open Refund: %1', $e->getMessage()));
        }
        return true;
    }
}

