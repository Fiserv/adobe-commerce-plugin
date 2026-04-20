<?php

namespace Fiserv\Payments\Api\OpenRefund;

use Fiserv\Payments\Api\Data\OpenRefund\OpenRefundInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

interface OpenRefundRepositoryInterface
{
    /**
     * @param int $entityId
     * @return \Fiserv\Payments\Api\Data\OpenRefund\OpenRefundInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get(int $entityId): OpenRefundInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Fiserv\Payments\Api\Data\OpenRefund\OpenRefundSearchResultInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * @param \Fiserv\Payments\Api\Data\OpenRefund\OpenRefundInterface $openRefund
     * @return \Fiserv\Payments\Api\Data\OpenRefund\OpenRefundInterface
     */
    public function save(OpenRefundInterface $openRefund): OpenRefundInterface;

    /**
     * @param \Fiserv\Payments\Api\Data\OpenRefund\OpenRefundInterface $openRefund
     * @return bool
     */
    public function delete(OpenRefundInterface $openRefund): bool;
}

