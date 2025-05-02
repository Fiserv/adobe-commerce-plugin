<?php

namespace Fiserv\Payments\Helper;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;

class DeclinedRealOrdersHelper {

	private $orderRepo;

	private $filterBuilder;

	private $searchBuilder;

	
	public function __construct(
		OrderRepositoryInterface $orderRepo,
		FilterBuilder $filterBuilder,
		SearchCriteriaBuilder $searchBuilder
	) {
		$this->orderRepo = $orderRepo;
		$this->filterBuilder = $filterBuilder;
		$this->searchBuilder = $searchBuilder;
	}

	public function getRealOrdersWithDeclines(array $orderIncrementIds)
	{
		$filter = $this->filterBuilder
		 ->setField('increment_id')
		 ->setConditionType('in')
		 ->setValue($orderIncrementIds)
		 ->create();

		$search = $this->searchBuilder
		 ->addFilters([$filter])
		 ->create();

		return $this->orderRepo->getList($search)->getItems();
	}
}
