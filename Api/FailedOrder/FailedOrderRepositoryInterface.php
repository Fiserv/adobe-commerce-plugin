<?php

namespace Fiserv\Payments\Api\FailedOrder;
use Fiserv\Payments\Api\Data\FailedOrder\FailedOrderInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface FailedOrderRepositoryInterface
{
	/**
	 * Retrieve failed order by ID
	 *
	 * @param int $id
	 * @return \Fiserv\Payments\Api\Data\FailedOrder\FailedOrderInterface
	 */
	public function get($id);

	/**
	 * Retrieve failed orders matching the specified criteria
	 *
	 * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
	 * @return \Fiserv\Payments\Api\Data\FailedOrder\FailedOrderSearchResultInterface
	 */
	public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);

	/**
	 * Save failed order
	 *
	 * @param \Fiserv\Payments\Api\Data\FailedOrder\FailedOrderInterface $failedOrder
	 * @return \Fiserv\Payments\Api\Data\FailedOrder\FailedOrderInterface
	 */
	public function save(\Fiserv\Payments\Api\Data\FailedOrder\FailedOrderInterface $failedOrder);

	/**
	 * Delete failed order
	 *
	 * @param \Fiserv\Payments\Api\Data\FailedOrder\FailedOrderInterface $failedOrder
	 * @return bool true on success
	 */
	public function delete(\Fiserv\Payments\Api\Data\FailedOrder\FailedOrderInterface $failedOrder);

	/**
	 * Retrieve a list of failed order by order increment ID
	 *
	 * @param string $orderIncrementId
	 * @return \Fiserv\Payments\Api\Data\FailedOrder\FailedOrderInterface[]
	 */
	public function getListByOrderIncrementId($orderIncrementId);
}
