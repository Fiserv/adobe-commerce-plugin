<?php

namespace Fiserv\Payments\Api\Data\Valuelink;

interface ValuelinkTransactionSearchResultInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    public function getItems();
}
