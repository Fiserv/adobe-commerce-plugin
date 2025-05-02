<?php

namespace Fiserv\Payments\Model;

use Fiserv\Payments\Api\Data\FailedOrder\FailedOrderInterface;
use Fiserv\Payments\Api\Data\FailedOrder\FailedOrderSearchResultInterfaceFactory;
use Fiserv\Payments\Api\FailedOrder\FailedOrderRepositoryInterface;
use Fiserv\Payments\Model\ResourceModel\FailedOrder as FailedOrderResource;
use Fiserv\Payments\Model\ResourceModel\FailedOrder\CollectionFactory as FailedOrderCollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;

class FailedOrderRepository implements FailedOrderRepositoryInterface
{
	protected $failedOrderFactory;
	protected $failedOrderResource;
	protected $searchResultsFactory;
	protected $collectionProcessor;
	protected $collectionFactory;
	private $filterBuilder;
	private $searchBuilder;

	public function __construct(
		\Fiserv\Payments\Model\FailedOrderFactory $failedOrderFactory,
		FailedOrderResource $failedOrderResource,
		FailedOrderSearchResultInterfaceFactory $searchResultsFactory,
		CollectionProcessorInterface $collectionProcessor,
		FailedOrderCollectionFactory $collectionFactory,
		FilterBuilder $filterBuilder,
		SearchCriteriaBuilder $searchBuilder
	) {
		$this->failedOrderFactory = $failedOrderFactory;
		$this->failedOrderResource = $failedOrderResource;
		$this->searchResultsFactory = $searchResultsFactory;
		$this->collectionProcessor = $collectionProcessor;
		$this->collectionFactory = $collectionFactory;
		$this->filterBuilder = $filterBuilder;
		$this->searchBuilder = $searchBuilder;
	}

	public function get($id)
	{
		$failedOrder = $this->failedOrderFactory->create();
		$this->failedOrderResource->load($failedOrder, $id);

		if (!$failedOrder->getId()) {
			throw new NoSuchEntityException(__('Failed order with id "%1" does not exist.', $id));
		}

		return $failedOrder;
	}

	public function getList(SearchCriteriaInterface $searchCriteria)
	{
		$collection = $this->collectionFactory->create();
		$this->collectionProcessor->process($searchCriteria, $collection);

		$searchResults = $this->searchResultsFactory->create();
		$searchResults->setSearchCriteria($searchCriteria);
		$searchResults->setItems($collection->getItems());
		$searchResults->setTotalCount($collection->getSize());

		return $searchResults;
	}

	public function save(FailedOrderInterface $failedOrder)
	{
		try {
			$this->failedOrderResource->save($failedOrder);
		} catch (\Exception $e) {
			throw new CouldNotSaveException(__('Could not save the failed order: %1', $e->getMessage()));
		}

		return $failedOrder;
	}

	public function delete(FailedOrderInterface $failedOrder)
	{
		try {
			$this->failedOrderResource->delete($failedOrder);
		} catch (\Exception $e) {
			throw new CouldNotDeleteException(__('Could not delete the failed order: %1', $e->getMessage()));
		}

		return true;
	}

	public function getListByOrderIncrementId($orderIncrementId)
	{
		$collection = $this->collectionFactory->create();
		$collection->addFieldToFilter('order_increment_id', $orderIncrementId);
		return $collection->getItems();
	}

	private function getFailedOrdersWithDeclines(array $orderIncrementIds)
	{
		$filter = $this->filterBuilder
		 ->setField(OrderModel::KEY_ORDER_INCREMENT_ID)
		 ->setConditionType('in')
		 ->setValue($orderIncrementIds)
		 ->create();

		$search = $this->searchBuilder
		 ->addFilters([$filter])
		 ->create();

		return $this->getList($search)->getItems();
	}
}
