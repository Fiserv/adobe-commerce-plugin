<?php

namespace Fiserv\Payments\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class CreateOpenRefundTable implements SchemaPatchInterface
{
    /**
     * @var SchemaSetupInterface
     */
    private SchemaSetupInterface $schemaSetup;

    public function __construct(SchemaSetupInterface $schemaSetup)
    {
        $this->schemaSetup = $schemaSetup;
    }

    public function apply(): self
    {
        $setup = $this->schemaSetup;
        $setup->startSetup();
        $connection = $setup->getConnection();
        $tableName  = $setup->getTable('fiserv_open_refund');

        if (!$connection->isTableExists($tableName)) {
            $table = $connection->newTable($tableName)
                ->addColumn(
                    'entity_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => false, 'identity' => true, 'primary' => true],
                    'Primary Key'
                )
                ->addColumn(
                    'amount',
                    Table::TYPE_DECIMAL,
                    '12,2',
                    ['unsigned' => false, 'nullable' => false],
                    'Refund Amount'
                )
                ->addColumn(
                    'currency_code',
                    Table::TYPE_TEXT,
                    10,
                    ['nullable' => false, 'default' => 'USD'],
                    'Currency Code'
                )
                ->addColumn(
                    'customer_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => true],
                    'Customer ID'
                )
                ->addColumn(
                    'customer_email',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => true],
                    'Customer Email (snapshotted at submission)'
                )
                ->addColumn(
                    'customer_name',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => true],
                    'Customer Full Name (snapshotted at submission)'
                )
                ->addColumn(
                    'admin_user_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => true],
                    'Admin User ID (populated server-side)'
                )
                ->addColumn(
                    'status',
                    Table::TYPE_TEXT,
                    20,
                    ['nullable' => false, 'default' => 'pending'],
                    'Record Status: pending / success / failed'
                )
                ->addColumn(
                    'transaction_id',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => true],
                    'CommerceHub Transaction ID'
                )
                ->addColumn(
                    'masked_card',
                    Table::TYPE_TEXT,
                    20,
                    ['nullable' => true],
                    'Last 4 digits from CommerceHub response'
                )
                ->addColumn(
                    'reference_transaction_id',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => true],
                    'Optional prior transaction ID for audit trail'
                )
                ->addColumn(
                    'notes',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => true],
                    'Free-text admin notes'
                )
                ->addColumn(
                    'created_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                    'Created At'
                )
                ->addIndex(
                    $setup->getIdxName('fiserv_open_refund', ['status']),
                    ['status']
                )
                ->addIndex(
                    $setup->getIdxName('fiserv_open_refund', ['customer_id']),
                    ['customer_id']
                )
                ->addIndex(
                    $setup->getIdxName('fiserv_open_refund', ['created_at']),
                    ['created_at']
                )
                ->setComment('Standalone Open Refund Records');

            $connection->createTable($table);
        }

        $setup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}

