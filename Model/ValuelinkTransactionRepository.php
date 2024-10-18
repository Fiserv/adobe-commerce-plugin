<?php

namespace Fiserv\Payments\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;

use Fiserv\Payments\Api\Valuelink\ValuelinkTransactionRepositoryInterface;
use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkTransactionResource;
use Fiserv\Payments\Api\Data\Valuelink\ValuelinkTransactionInterface;
use Fiserv\Payments\Api\Data\Valuelink\ValuelinkTransactionInterfaceFactory;
use Fiserv\Payments\Api\Data\Valuelink\ValuelinkTransactionSearchResultInterfaceFactory;
use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction\CollectionFactory;
		
class ValuelinkTransactionRepository implements ValuelinkTransactionRepositoryInterface
{
	private $valuelinkTransactionResource;
	
	private $valuelinkTransactionFactory;

	private $searchResultFactory;

	private $collectionProcessor;

	private $collectionFactory;
	
	public function __construct(
		ValuelinkTransactionResource $valuelinkTransactionResource,
		ValuelinkTransactionInterfaceFactory $valuelinkTransactionFactory,
		CollectionFactory $collectionFactory,
		ValuelinkTransactionSearchResultInterfaceFactory $searchResultFactory,
		CollectionProcessorInterface $collectionProcessor
	) {
		$this->valuelinkTransactionResource = $valuelinkTransactionResource;
		$this->valuelinkTransactionFactory = $valuelinkTransactionFactory;
		$this->collectionFactory = $collectionFactory;
		$this->searchResultFactory = $searchResultFactory;
		$this->collectionProcessor = $collectionProcessor;
	}

	public function get($id)
	{
		$entity = $this->valuelinkTransactionFactory->create();
		$this->valuelinkTransactionResource->load($entity, $id);
		return $entity;
	}

	public function getList(SearchCriteriaInterface $searchCriteria)
	{
		$collection = $this->collectionFactory->create();
		$this->collectionProcessor->process($searchCriteria, $collection);

		$searchResult = $this->searchResultFactory->create();
		$searchResult->setSearchCriteria($searchCriteria);
		$searchResult->setItems($collection->getItems());
		return $searchResult;
	}

	public function save(ValuelinkTransactionInterface $valuelinkDataObject)
	{
		try {
			$this->valuelinkTransactionResource->save($valuelinkDataObject);
		} catch (\Exception $e) {
			throw new CouldNotSaveException(__('Unable to save Valuelink transaction.'), $e);
		}
		return $valuelinkDataObject;
	}

	public function delete(ValuelinkTransactionInterface $valuelinkDataObject)
	{
		try {
			$this->valuelinkTransactionResource->delete($valuelinkDataObject);
		} catch (\Exception $e) {
			throw new CouldNotSaveException(__('Unable to delete Valuelink transaction.'), $e);
		}
		return true;
	}
}
