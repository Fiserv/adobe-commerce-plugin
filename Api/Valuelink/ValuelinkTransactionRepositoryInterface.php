<?php

namespace Fiserv\Payments\Api\Valuelink;

interface ValuelinkTransactionRepositoryInterface
{
	public function get($id);

	public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);
	
	public function save(\Fiserv\Payments\Api\Data\Valuelink\ValuelinkTransactionInterface $valuelinkDataObject);
	
	public function delete(\Fiserv\Payments\Api\Data\Valuelink\ValuelinkTransactionInterface $valuelinkDataObject);
}
