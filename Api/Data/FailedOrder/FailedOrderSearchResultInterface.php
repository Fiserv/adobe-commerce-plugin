<?php

namespace Fiserv\Payments\Api\Data\FailedOrder;

use Magento\Framework\Api\SearchResultsInterface;

interface FailedOrderSearchResultInterface extends SearchResultsInterface
{
	/**
	 * Get failed order list
	 *
	 * @return FailedOrderInterface[]
	 */
	public function getItems();

	/**
	 * Set failed order list
	 *
	 * @param FailedOrderInterface[] $items
	 * @return $this
	 */
	public function setItems(array $items);
}
