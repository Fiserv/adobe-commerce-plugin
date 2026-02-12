<?php
/**
 * Pending Inquiry Job Model
 * Represents a queued transaction inquiry job for async payment status checking
 */
namespace Fiserv\Payments\Model;

use Magento\Framework\Model\AbstractModel;
use Fiserv\Payments\Model\ResourceModel\PendingInquiryJob as PendingInquiryJobResource;

class PendingInquiryJob extends AbstractModel
{
    // Job statuses
    const STATUS_QUEUED = 'queued';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // Retry intervals in seconds
    const FIRST_RETRY_INTERVAL = 60;        // 1 minute after order placement
    const SECOND_RETRY_INTERVAL = 300;      // 5 minutes after first attempt
    const SUBSEQUENT_RETRY_INTERVAL = 1800; // 30 minutes for subsequent attempts

    /**
     * @var string
     */
    protected $_eventPrefix = 'fiserv_pending_inquiry_job';

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init(PendingInquiryJobResource::class);
    }

    /**
     * Get entity ID
     *
     * @return int|null
     */
    public function getEntityId()
    {
        return $this->getData('entity_id');
    }

    /**
     * Set entity ID
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId)
    {
        return $this->setData('entity_id', $entityId);
    }

    /**
     * Get Magento order ID
     *
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        $val = $this->getData('order_id');
        return $val !== null ? (int)$val : null;
    }

    /**
     * Set Magento order ID
     *
     * @param int|null $orderId
     * @return $this
     */
    public function setOrderId(?int $orderId): self
    {
        return $this->setData('order_id', $orderId);
    }

    /**
     * Get order increment ID
     *
     * @return string
     */
    public function getOrderIncrementId(): string
    {
        return (string)$this->getData('order_increment_id');
    }

    /**
     * Set order increment ID
     *
     * @param string $orderIncrementId
     * @return $this
     */
    public function setOrderIncrementId(string $orderIncrementId): self
    {
        return $this->setData('order_increment_id', $orderIncrementId);
    }

    /**
     * Get CommerceHub reference order ID
     *
     * @return string
     */
    public function getReferenceOrderId(): string
    {
        return (string)$this->getData('reference_order_id');
    }

    /**
     * Set CommerceHub reference order ID
     *
     * @param string $referenceOrderId
     * @return $this
     */
    public function setReferenceOrderId(string $referenceOrderId): self
    {
        return $this->setData('reference_order_id', $referenceOrderId);
    }

    /**
     * Get payment method code
     *
     * @return string
     */
    public function getPaymentMethod(): string
    {
        return (string)$this->getData('payment_method');
    }

    /**
     * Set payment method code
     *
     * @param string $paymentMethod
     * @return $this
     */
    public function setPaymentMethod(string $paymentMethod): self
    {
        return $this->setData('payment_method', $paymentMethod);
    }

    /**
     * Get job status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return (string)$this->getData('status');
    }

    /**
     * Set job status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self
    {
        return $this->setData('status', $status);
    }

    /**
     * Get transaction state
     *
     * @return string|null
     */
    public function getTransactionState(): ?string
    {
        return $this->getData('transaction_state');
    }

    /**
     * Set transaction state
     *
     * @param string|null $transactionState
     * @return $this
     */
    public function setTransactionState(?string $transactionState): self
    {
        return $this->setData('transaction_state', $transactionState);
    }

    /**
     * Get approval status
     *
     * @return string|null
     */
    public function getApprovalStatus(): ?string
    {
        return $this->getData('approval_status');
    }

    /**
     * Set approval status
     *
     * @param string|null $approvalStatus
     * @return $this
     */
    public function setApprovalStatus(?string $approvalStatus): self
    {
        return $this->setData('approval_status', $approvalStatus);
    }

    /**
     * Get number of attempts
     *
     * @return int
     */
    public function getAttempts(): int
    {
        return (int)$this->getData('attempts');
    }

    /**
     * Set number of attempts
     *
     * @param int $attempts
     * @return $this
     */
    public function setAttempts(int $attempts): self
    {
        return $this->setData('attempts', $attempts);
    }

    /**
     * Get max attempts
     *
     * @return int
     */
    public function getMaxAttempts(): int
    {
        return (int)$this->getData('max_attempts');
    }

    /**
     * Set max attempts
     *
     * @param int $maxAttempts
     * @return $this
     */
    public function setMaxAttempts(int $maxAttempts): self
    {
        return $this->setData('max_attempts', $maxAttempts);
    }

    /**
     * Get next run timestamp
     *
     * @return string
     */
    public function getNextRunAt(): string
    {
        return (string)$this->getData('next_run_at');
    }

    /**
     * Set next run timestamp
     *
     * @param string $nextRunAt
     * @return $this
     */
    public function setNextRunAt(string $nextRunAt): self
    {
        return $this->setData('next_run_at', $nextRunAt);
    }

    /**
     * Get last response JSON
     *
     * @return string|null
     */
    public function getLastResponse(): ?string
    {
        return $this->getData('last_response');
    }

    /**
     * Set last response JSON
     *
     * @param string|null $lastResponse
     * @return $this
     */
    public function setLastResponse(?string $lastResponse): self
    {
        return $this->setData('last_response', $lastResponse);
    }

    /**
     * Get error message
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->getData('error_message');
    }

    /**
     * Set error message
     *
     * @param string|null $errorMessage
     * @return $this
     */
    public function setErrorMessage(?string $errorMessage): self
    {
        return $this->setData('error_message', $errorMessage);
    }

    /**
     * Increment attempts and calculate next run time
     *
     * @return $this
     */
    public function incrementAttempts(): self
    {
        $attempts = $this->getAttempts() + 1;
        $this->setAttempts($attempts);

        // Calculate next run interval based on attempt count
        $interval = $this->getRetryInterval($attempts);
        // Use UTC time for consistency with Magento's database storage
        $nextRun = gmdate('Y-m-d H:i:s', time() + $interval);
        $this->setNextRunAt($nextRun);

        return $this;
    }

    /**
     * Get retry interval based on attempt number
     *
     * @param int $attempt
     * @return int Interval in seconds
     */
    public function getRetryInterval(int $attempt): int
    {
        switch ($attempt) {
            case 1:
                return self::FIRST_RETRY_INTERVAL;    // 1 min
            case 2:
                return self::SECOND_RETRY_INTERVAL;   // 5 min
            default:
                return self::SUBSEQUENT_RETRY_INTERVAL; // 30 min
        }
    }

    /**
     * Check if max attempts reached
     *
     * @return bool
     */
    public function hasReachedMaxAttempts(): bool
    {
        return $this->getAttempts() >= $this->getMaxAttempts();
    }

    /**
     * Mark job as completed
     *
     * @param string|null $transactionState
     * @param string|null $approvalStatus
     * @return $this
     */
    public function markCompleted(?string $transactionState = null, ?string $approvalStatus = null): self
    {
        $this->setStatus(self::STATUS_COMPLETED);
        if ($transactionState !== null) {
            $this->setTransactionState($transactionState);
        }
        if ($approvalStatus !== null) {
            $this->setApprovalStatus($approvalStatus);
        }
        return $this;
    }

    /**
     * Mark job as failed
     *
     * @param string $errorMessage
     * @return $this
     */
    public function markFailed(string $errorMessage): self
    {
        $this->setStatus(self::STATUS_FAILED);
        $this->setErrorMessage($errorMessage);
        return $this;
    }

    /**
     * Check if this is a PayPal-type payment (uses Order API endpoints)
     *
     * @return bool
     */
    public function isOrderType(): bool
    {
        $orderMethods = [
            'fiserv_affirm'
        ];
        $method = $this->getPaymentMethod();
        return $method !== '' && in_array($method, $orderMethods, true);
    }
}
