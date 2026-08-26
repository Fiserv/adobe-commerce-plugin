<?php
namespace Fiserv\Payments\Observer\PayByLink;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class DataAssignObserver extends AbstractDataAssignObserver
{
    const PBL_PAYMENT_ACTION = 'pbl_payment_action';
    const PBL_EXPR_DAYS = 'pbl_expr_days';
    const PBL_EXPR_HOURS = 'pbl_expr_hours';
    const PBL_EXPR_MINUTES = 'pbl_expr_minutes';
    const PBL_USE_DEFAULT_EXPIRY = 'pbl_use_default_expiry';
    const PBL_NOTES = 'pbl_notes';

    protected $additionalInformationList = [
        self::PBL_PAYMENT_ACTION,
        self::PBL_EXPR_DAYS,
        self::PBL_EXPR_HOURS,
        self::PBL_EXPR_MINUTES,
        self::PBL_USE_DEFAULT_EXPIRY,
        self::PBL_NOTES,
    ];

    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        foreach ($this->additionalInformationList as $key) {
            if (isset($additionalData[$key])) {
                $value = $additionalData[$key];
                
                // Recursively extract scalar from nested arrays
                // Form submissions can produce: pbl_expr_days = [0], ["0"], [[0]], etc.
                while (is_array($value) && !empty($value)) {
                    $value = reset($value);
                }

                // If after extraction we still have an array (shouldn't happen), use default
                if (is_array($value)) {
                    $value = '';
                }
                
                // Ensure scalar values - some form submissions might pass arrays
                // Cast to appropriate type to prevent serialization issues
                if ($key === self::PBL_EXPR_DAYS || $key === self::PBL_EXPR_HOURS || $key === self::PBL_EXPR_MINUTES) {
                    $value = (int)$value;
                } elseif ($key === self::PBL_USE_DEFAULT_EXPIRY) {
                    $value = (string)(int)(bool)$value;
                } else {
                    // Ensure all values are strings
                    $value = (string)$value;
                }
                
                $paymentInfo->setAdditionalInformation($key, $value);
            }
        }
    }
}
