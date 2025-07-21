<?php

namespace Fiserv\Payments\Api\Data\FailedTransaction;

use Magento\Framework\Api\SearchResultsInterface;

interface FailedTransactionSearchResultInterface extends SearchResultsInterface
{
	/**
	 * Get failed transactions list
	 *
	 * @return FailedTransactionInterface[]
	 */
	public function getItems();

	/**
	 * Set failed transactions list
	 *
	 * @param FailedTransactionInterface[] $items
	 * @return $this
	 */
	public function setItems(array $items);
}