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
use Fiserv\Payments\Model\FailedOrder as OrderModel;
use Magento\Framework\App\ResourceConnection;

class FailedOrderRepository implements FailedOrderRepositoryInterface
{
	protected $failedOrderFactory;
	protected $failedOrderResource;
	protected $searchResultsFactory;
	protected $collectionProcessor;
	protected $collectionFactory;
	private $filterBuilder;
	private $searchBuilder;
	private $resourceConnection;

	public function __construct(
		\Fiserv\Payments\Model\FailedOrderFactory $failedOrderFactory,
		FailedOrderResource $failedOrderResource,
		FailedOrderSearchResultInterfaceFactory $searchResultsFactory,
		CollectionProcessorInterface $collectionProcessor,
		FailedOrderCollectionFactory $collectionFactory,
		FilterBuilder $filterBuilder,
		SearchCriteriaBuilder $searchBuilder,
		ResourceConnection $resourceConnection
	) {
		$this->failedOrderFactory = $failedOrderFactory;
		$this->failedOrderResource = $failedOrderResource;
		$this->searchResultsFactory = $searchResultsFactory;
		$this->collectionProcessor = $collectionProcessor;
		$this->collectionFactory = $collectionFactory;
		$this->filterBuilder = $filterBuilder;
		$this->searchBuilder = $searchBuilder;
		$this->resourceConnection = $resourceConnection;
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

	public function getFailedOrdersWithDeclines(array $orderIncrementIds)
	{
		if (empty($orderIncrementIds)) {
			return [];
		}

		$connection = $this->resourceConnection->getConnection();
		$tableName = $this->resourceConnection->getTableName('failed_order');

		// Subquery: Get the minimum date_time per order_increment_id
		$subSelect = $connection->select()
			  ->from(
				  ['fo' => $tableName],
				  ['order_increment_id', 'min_date_time' => new \Zend_Db_Expr('MIN(date_time)')]
			  )
			  ->where('fo.order_increment_id IN (?)', $orderIncrementIds)
			  ->group('fo.order_increment_id');

		// Main query: Join with subquery to get full rows
		$select = $connection->select()
		       ->from(['main_table' => $tableName])
		       ->joinInner(
			       ['min_table' => $subSelect],
			       'main_table.order_increment_id = min_table.order_increment_id AND main_table.date_time = min_table.min_date_time',
			       []
		       )
		       ->order('main_table.order_increment_id ASC');

		return $connection->fetchAll($select);
	}

	public function getAllOrderState() {
		$connection = $this->resourceConnection->getConnection();
		$query = "SELECT DISTINCT order_state FROM failed_order";
		return $connection->fetchCol($query);
	}
}
