<?php

namespace Fiserv\Payments\Block\Sales\Order\Adminhtml\CreditMemo;

use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;

class SurchargeEstimate extends Template
{
    /** Returns the order attached to the current credit memo — used by the template. */
    public function getOrder(): ?\Magento\Sales\Model\Order
    {
        return ($cm = $this->getCreditMemo()) ? $cm->getOrder() : null;
    }

    /**
     * Returns the proportional surcharge refund for the items currently being credited which
     * recalculates dynamically whenever the admin changes quantities
     */
    public function getEstimatedSurchargeRefund(): ?float
    {
        $creditMemo = $this->getCreditMemo();
        if (!$creditMemo) {
            return null;
        }

        $order = $creditMemo->getOrder();
        $payment = $order->getPayment();
        if (!$payment) {
            return null;
        }

        $invoicedSurcharge = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_INVOICED_KEY);
        if ($invoicedSurcharge <= 0) {
            return null;
        }

        $orderMerchandiseTotal = (float)$order->getGrandTotal() - $invoicedSurcharge;
        if ($orderMerchandiseTotal <= 0) {
            return null;
        }

        $refundMerchandiseTotal = (float)$creditMemo->getSubtotal() + (float)$creditMemo->getShippingAmount();
        if ($refundMerchandiseTotal <= 0) {
            return null;
        }

        $proportionalSurchargeRefund = round($refundMerchandiseTotal / $orderMerchandiseTotal * $invoicedSurcharge, 4);

        return $proportionalSurchargeRefund > 0 ? $proportionalSurchargeRefund : null;
    }

    /** Registers this block as a totals row inserted just before Grand Total.*/
    public function initTotals(): self
    {
        if ($this->getEstimatedSurchargeRefund() === null) {
            return $this;
        }

        $this->getParentBlock()->addTotalBefore(
            new DataObject([
                'code' => $this->getNameInLayout(),
                'block_name' => $this->getNameInLayout(),
            ]),
            'grand_total'
        );

        return $this;
    }

    private function getCreditMemo(): ?\Magento\Sales\Model\Order\Creditmemo
    {
        return $this->getParentBlock()->getCreditmemo();
    }
}
