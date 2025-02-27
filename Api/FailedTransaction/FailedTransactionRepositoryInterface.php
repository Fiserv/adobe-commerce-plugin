<?php

namespace Fiserv\Payments\Api\FailedTransaction;
use Fiserv\Payments\Api\Data\FailedTransaction\FailedTransactionInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface FailedTransactionRepositoryInterface
{
	/**
	 * Retrieve failed transaction by ID
	 *
	 * @param int $id
	 * @return \Fiserv\Payments\Api\Data\FailedTransaction\FailedTransactionInterface
	 */
	public function get($id);

	/**
	 * Retrieve failed transactions matching the specified criteria
	 *
	 * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
	 * @return \Fiserv\Payments\Api\Data\FailedTransaction\FailedTransactionSearchResultInterface
	 */
	public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);

	/**
	 * Save failed transaction
	 *
	 * @param \Fiserv\Payments\Api\Data\FailedTransaction\FailedTransactionInterface $failedTransaction
	 * @return \Fiserv\Payments\Api\Data\FailedTransaction\FailedTransactionInterface
	 */
	public function save(\Fiserv\Payments\Api\Data\FailedTransaction\FailedTransactionInterface $failedTransaction);

	/**
	 * Delete failed transaction
	 *
	 * @param \Fiserv\Payments\Api\Data\FailedTransaction\FailedTransactionInterface $failedTransaction
	 * @return bool true on success
	 */
	public function delete(\Fiserv\Payments\Api\Data\FailedTransaction\FailedTransactionInterface $failedTransaction);

	/**
	 * Retrieve a list of failed transactions by order increment ID
	 *
	 * @param string $orderIncrementId
	 * @return \Fiserv\Payments\Api\Data\FailedTransaction\FailedTransactionInterface[]
	 */
	public function getListByOrderIncrementId($orderIncrementId);
}
