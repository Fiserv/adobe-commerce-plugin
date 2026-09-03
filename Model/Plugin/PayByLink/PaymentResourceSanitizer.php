<?php

namespace Fiserv\Payments\Model\Plugin\PayByLink;

use Fiserv\Payments\Model\PayByLink\Method;
use Magento\Framework\Model\AbstractModel;

/**
 * Final DB-write guard for the PayByLink payment row only.
 *
 * The `additional_information` column is JSON-serialized automatically by
 * Magento, but other columns (e.g. `additional_data`) are NOT. If an array
 * reaches a non-serialized column it triggers:
 *   "Array to string conversion in .../DB/Adapter/Pdo/Mysql.php"
 *
 * This plugin runs at the resource-model save boundary (the last point before
 * the SQL bind). It is intentionally scoped to the PayByLink method so it never
 * mutates rows belonging to other payment methods.
 */
class PaymentResourceSanitizer
{
    /**
     * PayByLink temporary/known fields that may arrive as arrays from the admin
     * form and must be flattened to scalars before persistence. We deliberately
     * do NOT touch arbitrary columns to avoid corrupting other data.
     *
     * @var string[]
     */
    private const PBL_FIELDS = [
        'additional_data',
        'pbl_expr_days',
        'pbl_expr_hours',
        'pbl_expr_minutes',
        'pbl_use_default_expiry',
        'pbl_page_id',
        'pbl_notes',
        'pbl_payment_action',
        'pbl_expires_in_minutes',
    ];

    /**
     * @param mixed $subject
     * @param AbstractModel $object
     * @return array
     */
    public function beforeSave($subject, AbstractModel $object): array
    {
        if ((string) $object->getData('method') !== Method::PAYMENT_METHOD_CODE) {
            return [$object];
        }

        foreach (self::PBL_FIELDS as $field) {
            $value = $object->getData($field);
            if (is_array($value) || is_object($value)) {
                $object->setData($field, $this->flatten($value));
            }
        }

        return [$object];
    }

    /**
     * Reduce a nested array/object to a scalar string safely.
     *
     * @param mixed $value
     * @return string
     */
    private function flatten($value): string
    {
        while (is_array($value) && !empty($value)) {
            $value = reset($value);
        }

        if (is_array($value) || is_object($value)) {
            return '';
        }

        return (string) $value;
    }
}
