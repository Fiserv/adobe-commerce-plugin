<?php

namespace Fiserv\Payments\Model\Plugin\PayByLink;

use Fiserv\Payments\Model\PayByLink\Method;
use Magento\Quote\Model\Quote\Payment as QuotePayment;

class PaymentSanitizer
{
    private const INT_FIELDS = [
        'pbl_expr_days',
        'pbl_expr_hours',
        'pbl_expr_minutes',
        'pbl_expires_in_minutes',
    ];

    private const BOOL_STRING_FIELDS = [
        'pbl_use_default_expiry',
    ];

    /**
     * Normalize admin posted additional_data before quote payment import.
     *
     * @param QuotePayment $subject
     * @param array $data
     * @return array
     */
    public function beforeImportData(QuotePayment $subject, array $data): array
    {
        $method = (string)($data['method'] ?? $subject->getMethod());
        if ($method !== Method::PAYMENT_METHOD_CODE) {
            return [$data];
        }

        if (!isset($data['additional_data']) || !is_array($data['additional_data'])) {
            return [$data];
        }

        $data['additional_data'] = $this->sanitizeArray($data['additional_data']);

        return [$data];
    }

    /**
     * Final safety net before quote/order payment save.
     *
     * The subject is intentionally left untyped: this plugin is registered on
     * both Magento\Quote\Model\Quote\Payment and Magento\Sales\Model\Order\Payment,
     * whose interceptors do not share a single concrete base class across all
     * Magento versions. A strict concrete type-hint here throws a TypeError when
     * the order-payment interceptor is passed (e.g. during the PBL retry save).
     *
     * @param \Magento\Payment\Model\InfoInterface $subject
     * @return array
     */
    public function beforeSave($subject): array
    {
        if (!$this->isPayByLinkContext($subject)) {
            return [];
        }

        $additionalInfo = $subject->getAdditionalInformation();
        if (!is_array($additionalInfo)) {
            $additionalInfo = [];
        }

        $subject->setData('additional_information', $this->sanitizeArray($additionalInfo));

        $additionalData = $subject->getData('additional_data');
        if (is_array($additionalData) || is_object($additionalData)) {
            $subject->setData('additional_data', '');
        }

        return [];
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $subject
     * @return bool
     */
    private function isPayByLinkContext($subject): bool
    {
        if ((string)$subject->getMethod() === Method::PAYMENT_METHOD_CODE) {
            return true;
        }

        $additionalInfo = $subject->getAdditionalInformation();
        if (is_array($additionalInfo)) {
            foreach ($additionalInfo as $key => $value) {
                if (strpos((string)$key, 'pbl_') === 0) {
                    return true;
                }
            }
        }

        $additionalData = $subject->getData('additional_data');
        if (is_array($additionalData)) {
            foreach ($additionalData as $key => $value) {
                if (strpos((string)$key, 'pbl_') === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array $values
     * @return array
     */
    private function sanitizeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            $scalar = $this->extractScalar($value);

            if (in_array($key, self::INT_FIELDS, true)) {
                $values[$key] = (int)$scalar;
                continue;
            }

            if (in_array($key, self::BOOL_STRING_FIELDS, true)) {
                $values[$key] = (string)(int)(bool)$scalar;
                continue;
            }

            if (is_array($scalar) || is_object($scalar)) {
                $values[$key] = '';
                continue;
            }

            $values[$key] = (string)$scalar;
        }

        return $values;
    }

    /**
     * Flatten nested arrays like [0], [["1"]], etc.
     *
     * @param mixed $value
     * @return mixed
     */
    private function extractScalar($value)
    {
        while (is_array($value) && !empty($value)) {
            $value = reset($value);
        }

        return $value;
    }
}