<?php
namespace Fiserv\Payments\Gateway\Response\PayPal;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;

class RefundHandler implements HandlerInterface
{
    public function handle(array $handlingSubject, array $response)
    {
        if (!isset($handlingSubject['payment']) || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }
        $payment = $handlingSubject['payment']->getPayment();

        // TODO: Handle PayPal refund response
        if (isset($response['id'])) {
            $payment->setTransactionId($response['id']);
        }
        $payment->setAdditionalInformation('paypal_refund_response', $response);

        // Mark transaction as closed if refund is completed
        if (isset($response['status']) && $response['status'] === 'COMPLETED') {
            $payment->setIsTransactionClosed(true);
        } else {
            $payment->setIsTransactionClosed(false);
        }
    }
}
