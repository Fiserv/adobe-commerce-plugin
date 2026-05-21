<?php
/**
 * Collection for Pending Inquiry Job
 */
namespace Fiserv\Payments\Model\ResourceModel\PendingInquiryJob;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Fiserv\Payments\Model\PendingInquiryJob;
use Fiserv\Payments\Model\ResourceModel\PendingInquiryJob as PendingInquiryJobResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'fiserv_pending_inquiry_job_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'pending_inquiry_job_collection';

    /**
     * Initialize collection
     */
    protected function _construct()
    {
        $this->_init(PendingInquiryJob::class, PendingInquiryJobResource::class);
    }

    /**
     * Filter by queued status ready to run
     *
     * @return $this
     */
    public function addReadyToRunFilter(): self
    {
        $this->addFieldToFilter('status', PendingInquiryJob::STATUS_QUEUED);
        // Use UTC time for comparison since next_run_at is stored in UTC
        $this->addFieldToFilter('next_run_at', ['lteq' => gmdate('Y-m-d H:i:s')]);
        return $this;
    }

    /**
     * Filter by status
     *
     * @param string|array $status
     * @return $this
     */
    public function addStatusFilter($status): self
    {
        $this->addFieldToFilter('status', is_array($status) ? ['in' => $status] : $status);
        return $this;
    }

    /**
     * Filter by payment method
     *
     * @param string|array $paymentMethod
     * @return $this
     */
    public function addPaymentMethodFilter($paymentMethod): self
    {
        $this->addFieldToFilter('payment_method', is_array($paymentMethod) ? ['in' => $paymentMethod] : $paymentMethod);
        return $this;
    }

    /**
     * Order by next run time ascending
     *
     * @return $this
     */
    public function orderByNextRun(): self
    {
        $this->setOrder('next_run_at', 'ASC');
        return $this;
    }
}
