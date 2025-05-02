<?php

namespace Fiserv\Payments\Model;

use Fiserv\Payments\Api\Data\FailedTransaction\FailedTransactionInterface;
use Fiserv\Payments\Api\Data\FailedTransaction\FailedTransactionSearchResultInterfaceFactory;
use Fiserv\Payments\Api\FailedTransaction\FailedTransactionRepositoryInterface;
use Fiserv\Payments\Model\ResourceModel\FailedTransaction as FailedTransactionResource;
use Fiserv\Payments\Model\ResourceModel\FailedTransaction\CollectionFactory as FailedTransactionCollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\App\ResourceConnection;

class FailedTransactionRepository implements FailedTransactionRepositoryInterface
{
	protected $failedTransactionFactory;
	protected $failedTransactionResource;
	protected $searchResultsFactory;
	protected $collectionProcessor;
	protected $collectionFactory;
	private $resourceConnection;

	public function __construct(
		\Fiserv\Payments\Model\FailedTransactionFactory $failedTransactionFactory,
		FailedTransactionResource $failedTransactionResource,
		FailedTransactionSearchResultInterfaceFactory $searchResultsFactory,
		CollectionProcessorInterface $collectionProcessor,
		FailedTransactionCollectionFactory $collectionFactory,
		ResourceConnection $resourceConnection
	) {
		$this->failedTransactionFactory = $failedTransactionFactory;
		$this->failedTransactionResource = $failedTransactionResource;
		$this->searchResultsFactory = $searchResultsFactory;
		$this->collectionProcessor = $collectionProcessor;
		$this->collectionFactory = $collectionFactory;
		$this->resourceConnection = $resourceConnection;
	}

	public function get($id)
	{
		$failedTransaction = $this->failedTransactionFactory->create();
		$this->failedTransactionResource->load($failedTransaction, $id);

		if (!$failedTransaction->getId()) {
			throw new NoSuchEntityException(__('Failed Transaction with id "%1" does not exist.', $id));
		}

		return $failedTransaction;
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

	public function save(FailedTransactionInterface $failedTransaction)
	{
		try {
			$this->failedTransactionResource->save($failedTransaction);
		} catch (\Exception $e) {
			throw new CouldNotSaveException(__('Could not save the failed transaction: %1', $e->getMessage()));
		}

		return $failedTransaction;
	}

	public function delete(FailedTransactionInterface $failedTransaction)
	{
		try {
			$this->failedTransactionResource->delete($failedTransaction);
		} catch (\Exception $e) {
			throw new CouldNotDeleteException(__('Could not delete the failed transaction: %1', $e->getMessage()));
		}

		return true;
	}

	public function getListByOrderIncrementId($orderIncrementId)
	{
		$collection = $this->collectionFactory->create();
		$collection->addFieldToFilter('order_increment_id', $orderIncrementId);
		return $collection->getItems();
	}

	private function getFailedTransactionData(array $orderIncrementIds)
	{
		$connection = $this->resourceConnection->getConnection();
		$orderIncrementIdsString = implode(',', $orderIncrementIds);
		$query = "
			SELECT ft.order_increment_id, ft.date_time AS oldest_date_time, ft.remote_ip
			FROM failed_transaction ft
			INNER JOIN (
				SELECT order_increment_id, MIN(date_time) AS oldest_date_time
				FROM failed_transaction
				WHERE order_increment_id IN ($orderIncrementIdsString)
				GROUP BY order_increment_id
			) AS subquery
			ON ft.order_increment_id = subquery.order_increment_id AND ft.date_time = subquery.oldest_date_time
		";

		return $connection->fetchAll($query);
	}
}