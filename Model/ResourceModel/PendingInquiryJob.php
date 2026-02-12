<?php
/**
 * Resource Model for Pending Inquiry Job
 */
namespace Fiserv\Payments\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PendingInquiryJob extends AbstractDb
{
    /**
     * Table name
     */
    const TABLE_NAME = 'fiserv_pending_inquiry_job';

    /**
     * Primary key field name
     */
    const ID_FIELD_NAME = 'entity_id';

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, self::ID_FIELD_NAME);
    }

    /**
     * Get pending jobs that are ready to run
     *
     * @param int $limit
     * @return array
     */
    public function getReadyJobs(int $limit = 50): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('status = ?', \Fiserv\Payments\Model\PendingInquiryJob::STATUS_QUEUED)
            // Use UTC time for comparison since next_run_at is stored in UTC
            ->where('next_run_at <= ?', gmdate('Y-m-d H:i:s'))
            ->order('next_run_at ASC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    /**
     * Get job by order increment ID
     *
     * @param string $orderIncrementId
     * @return array|null
     */
    public function getByOrderIncrementId(string $orderIncrementId): ?array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('order_increment_id = ?', $orderIncrementId)
            ->limit(1);

        $result = $connection->fetchRow($select);
        return $result ?: null;
    }

    /**
     * Check if a job already exists for the given order
     *
     * @param int $orderId
     * @return bool
     */
    public function jobExistsForOrder(?int $orderId): bool
    {
        if ($orderId === null) {
            return false;
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['entity_id'])
            ->where('order_id = ?', $orderId)
            ->limit(1);

        return (bool)$connection->fetchOne($select);
    }

    /**
     * Delete completed jobs older than specified days
     *
     * @param int $days
     * @return int Number of deleted rows
     */
    public function cleanupOldJobs(int $days = 30): int
    {
        $connection = $this->getConnection();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $connection->delete(
            $this->getMainTable(),
            [
                'status IN (?)' => [
                    \Fiserv\Payments\Model\PendingInquiryJob::STATUS_COMPLETED,
                    \Fiserv\Payments\Model\PendingInquiryJob::STATUS_FAILED
                ],
                'updated_at < ?' => $cutoffDate
            ]
        );
    }
}
