<?php
namespace Fiserv\Payments\Api\SubscriptionOrder;

use Fiserv\Payments\Api\Data\SubscriptionOrder\SubscriptionOrderInterface;
use Fiserv\Payments\Api\Data\SubscriptionOrder\SubscriptionOrderSearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface SubscriptionOrderRepositoryInterface
{
	/**
	 * @param SubscriptionOrderInterface $order
	 * @return SubscriptionOrderInterface
	 * @throws \Magento\Framework\Exception\CouldNotSaveException
	 */
	public function save(SubscriptionOrderInterface $order);

	/**
	 * @param int $id
	 * @return SubscriptionOrderInterface
	 * @throws \Magento\Framework\Exception\NoSuchEntityException
	 */
	public function getById($id);

	/**
	 * @param SearchCriteriaInterface $searchCriteria
	 * @return SubscriptionOrderSearchResultInterface
	 */
	public function getList(SearchCriteriaInterface $searchCriteria);

	/**
	 * @param SubscriptionOrderInterface $order
	 * @return bool
	 * @throws \Magento\Framework\Exception\CouldNotDeleteException
	 */
	public function delete(SubscriptionOrderInterface $order);

	/**
	 * @param int $id
	 * @return bool
	 * @throws \Magento\Framework\Exception\NoSuchEntityException
	 * @throws \Magento\Framework\Exception\CouldNotDeleteException
	 */
	public function deleteById($id);
}
