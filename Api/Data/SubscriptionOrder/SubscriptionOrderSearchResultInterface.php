<?php

namespace Fiserv\Payments\Api\Data\SubscriptionOrder;

use Magento\Framework\Api\SearchResultsInterface;

interface SubscriptionOrderSearchResultInterface extends SearchResultsInterface
{
	/**
	 * Get subscription orders list
	 *
	 * @return \Fiserv\Payments\Api\Data\SubscriptionOrder\SubscriptionOrderInterface[]
	 */
	public function getItems();

	/**
	 * Set subscription orders list
	 *
	 * @param \Fiserv\Payments\Api\Data\SubscriptionOrder\SubscriptionOrderInterface[] $items
	 * @return $this
	 */
	public function setItems(array $items);
}
