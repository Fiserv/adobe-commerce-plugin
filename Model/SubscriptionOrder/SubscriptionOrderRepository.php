<?php
declare(strict_types=1);
namespace Fiserv\Payments\Model\SubscriptionOrder;

use Fiserv\Payments\Api\SubscriptionOrder\SubscriptionOrderRepositoryInterface;
use Fiserv\Payments\Api\Data\SubscriptionOrder\SubscriptionOrderInterface;
use Fiserv\Payments\Api\Data\SubscriptionOrder\SubscriptionOrderSearchResultInterfaceFactory;
use Fiserv\Payments\Model\Subscription\OrderFactory;
use Fiserv\Payments\Model\ResourceModel\Subscription\Order as OrderResource;
use Fiserv\Payments\Model\ResourceModel\Subscription\Order\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;

class SubscriptionOrderRepository implements SubscriptionOrderRepositoryInterface
{
	public function __construct(
		private readonly OrderFactory $orderFactory,
		private readonly OrderResource $orderResource,
		private readonly CollectionFactory $collectionFactory,
		private readonly SubscriptionOrderSearchResultInterfaceFactory $searchResultFactory,
		private readonly CollectionProcessorInterface $collectionProcessor
	) {}

	public function save(SubscriptionOrderInterface $order)
	{
		try {
			$this->orderResource->save($order);
		} catch (\Exception $e) {
			throw new CouldNotSaveException(__($e->getMessage()));
		}
		return $order;
	}

	public function getById($id)
	{
		$order = $this->orderFactory->create();
		$this->orderResource->load($order, $id);
		if (!$order->getEntityId()) {
			throw new NoSuchEntityException(
				__('Subscription order with id "%1" does not exist.', $id)
			);
		}
		return $order;
	}

	public function getList(SearchCriteriaInterface $searchCriteria)
	{
		$collection = $this->collectionFactory->create();
		$this->collectionProcessor->process($searchCriteria, $collection);

		$searchResults = $this->searchResultFactory->create();
		$searchResults->setSearchCriteria($searchCriteria);
		$searchResults->setItems($collection->getItems());
		$searchResults->setTotalCount($collection->getSize());

		return $searchResults;
	}

	public function delete(SubscriptionOrderInterface $order)
	{
		try {
			$this->orderResource->delete($order);
		} catch (\Exception $e) {
			throw new CouldNotDeleteException(__($e->getMessage()));
		}
		return true;
	}

	public function deleteById($id)
	{
		return $this->delete($this->getById($id));
	}

	public function getByOrderIncrementId(string $incrementId): ?SubscriptionOrderInterface
	{
		$incrementId = trim($incrementId);
		if ($incrementId === '') {
			return null;
		}

		$collection = $this->collectionFactory->create();
		$collection->addFieldToFilter(SubscriptionOrderInterface::ORDER_INCREMENT_ID, $incrementId);
		$collection->setPageSize(1);

		$item = $collection->getFirstItem();
		return ($item && $item->getEntityId()) ? $item : null;
	}

	/**
	 * Tolerant head lookup:
	 * - Chain head increment ID is the root (no "-###").
	 * - Prefer the row whose ORDER_INCREMENT_ID == root (the true FIRST).
	 * - Fallback to the oldest row for that root if data is messy.
	 */
	public function getChainHeadByOriginalIncrement(string $originalIncrement): ?SubscriptionOrderInterface
	{
		$originalIncrement = trim($originalIncrement);
		if ($originalIncrement === '') {
			return null;
		}

		// Per business rule, the chain head increment id is the root (no "-###")
		$root = $this->rootFromIncrementId($originalIncrement);

		$collection = $this->collectionFactory->create();
		$collection->addFieldToFilter(SubscriptionOrderInterface::ORIGINAL_ORDER_INCREMENT, $root);

		// Prefer the row whose order_increment_id equals the root (the "FIRST" order)
		$collection->addFieldToFilter(SubscriptionOrderInterface::ORDER_INCREMENT_ID, $root);
		$collection->setPageSize(1);

		$head = $collection->getFirstItem();
		if ($head && $head->getEntityId()) {
			return $head;
		}

		// Fallback: if data is messy, return the oldest row for that root
		$fallback = $this->collectionFactory->create();
		$fallback->addFieldToFilter(SubscriptionOrderInterface::ORIGINAL_ORDER_INCREMENT, $root);
		$fallback->setOrder(\Fiserv\Payments\Api\Data\SubscriptionOrder\SubscriptionOrderInterface::CREATED_AT, 'ASC');
		$fallback->setPageSize(1);

		$oldest = $fallback->getFirstItem();
		return ($oldest && $oldest->getEntityId()) ? $oldest : null;
	}

	/**
	 * Normalize the chain key and resolve the head based on the root increment.
	 */
	public function getChainHead(SubscriptionOrderInterface $row): SubscriptionOrderInterface
	{
		$inc = (string)($row->getOriginalOrderIncrement() ?: $row->getOrderIncrementId());
		$inc = trim($inc);

		if ($inc === '') {
			return $row;
		}

		$root = $this->rootFromIncrementId($inc);
		$head = $this->getChainHeadByOriginalIncrement($root);

		return $head ?: $row;
	}


	/**
	 * Helper: strip "-###" suffix to get the root increment id.
	 */
	private function rootFromIncrementId(string $inc): string
	{
		return preg_replace('/-\d+$/', '', $inc) ?: $inc;
	}
}