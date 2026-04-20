<?php

namespace Fiserv\Payments\Api\Data\OpenRefund;

use Magento\Framework\Api\SearchResultsInterface;

interface OpenRefundSearchResultInterface extends SearchResultsInterface
{
    /**
     * @return \Fiserv\Payments\Api\Data\OpenRefund\OpenRefundInterface[]
     */
    public function getItems();

    /**
     * @param \Fiserv\Payments\Api\Data\OpenRefund\OpenRefundInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}

