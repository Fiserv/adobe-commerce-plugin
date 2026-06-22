<?php

namespace Fiserv\Payments\Block\Sales\Order\Adminhtml\CreditMemo;

use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;

class SurchargeEstimate extends Template
{

    /** Returns the order attached to the current credit memo session */
    public function getOrder(): ?\Magento\Sales\Model\Order
    {
        $creditmemo = $this->getParentBlock()->getCreditmemo();
        return $creditmemo ? $creditmemo->getOrder() : null;
    }

    /** Returns the current (in-memory) credit memo being constructed. */
    public function getCreditMemo(): ?\Magento\Sales\Model\Order\Creditmemo
    {
        return $this->getParentBlock()->getCreditmemo();
    }

    public function getEstimatedSurchargeRefund(): ?float
    {
        $order = $this->getOrder();
        if (!$order) {
            return null;
        }

        $payment = $order->getPayment();
        if (!$payment) {
            return null;
        }

        $surcharge = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);
        if ($surcharge <= 0) {
            return null;
        }

        $orderGrandTotal = (float)$order->getGrandTotal();
        if ($orderGrandTotal <= 0) {
            return null;
        }

        $creditMemo = $this->getCreditMemo();
        if (!$creditMemo) {
            return null;
        }

        $creditMemoTotal = (float)$creditMemo->getGrandTotal();
        $estimated = round($creditMemoTotal * $surcharge / $orderGrandTotal, 4);

        return $estimated > 0 ? $estimated : null;
    }

    /** Registers this block as a totals row inserted just before Grand Total.*/
    public function initTotals(): self
    {
        if ($this->getEstimatedSurchargeRefund() === null) {
            return $this;
        }

        $total = new DataObject([
            'code' => $this->getNameInLayout(),
            'block_name' => $this->getNameInLayout(),
        ]);

        $this->getParentBlock()->addTotalBefore($total, 'grand_total');

        return $this;
    }
}
