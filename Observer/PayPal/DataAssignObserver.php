<?php

namespace Fiserv\Payments\Observer\PayPal;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Observer for assigning PayPal-specific payment data to the payment info instance.
 */
class DataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * Keys for PayPal-specific additional information.
     */
    public const ORDER_ID      = 'paypal_order_id';
    public const REF_TXN_KEY   = 'paypal_order_id';
    public const EMAIL         = 'paypal_email';
    public const TRANSACTION_ID = 'paypal_transaction_id';
    public const INTENT        = 'paypal_intent';

    /**
     * List of PayPal data keys to assign.
     *
     * @var string[]
     */
    protected $paypalInfoKeys = [
        self::ORDER_ID,
        self::REF_TXN_KEY,
        self::EMAIL,
        self::TRANSACTION_ID,
        self::INTENT
    ];

    /**
     * Assigns PayPal-specific data from additional_data to the payment info instance.
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        foreach ($this->paypalInfoKeys as $key) {
            if (array_key_exists($key, $additionalData)) {
                $paymentInfo->setAdditionalInformation($key, $additionalData[$key]);
            }
        }
    }
}