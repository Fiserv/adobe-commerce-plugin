<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Block\Sales\Order\Adminhtml\Invoice;

use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;


class SurchargeEstimate extends \Magento\Sales\Block\Adminhtml\Order\Totals\Item
{
    /** Returns the in-progress invoice from the parent totals block. */
    public function getInvoice(): ?Invoice
    {
        $parent = $this->getParentBlock();
        if ($parent && method_exists($parent, 'getInvoice')) {
            return $parent->getInvoice();
        }
        if ($parent && method_exists($parent, 'getSource')) {
            $source = $parent->getSource();
            if ($source instanceof Invoice) {
                return $source;
            }
        }
        return null;
    }

    /** Returns the order attached to the in-progress invoice. */
    public function getOrder(): ?Order
    {
        return ($invoice = $this->getInvoice()) ? $invoice->getOrder() : null;
    }

    /**
     * Returns the proportional surcharge for the items currently being invoiced
     * which recalculates whenever the admin changes quantities
     */
    public function getSurchargeAmount(): ?float
    {
        $invoice = $this->getInvoice();
        if (!$invoice) {
            return null;
        }

        $order = $invoice->getOrder();
        $payment = $order->getPayment();
        if (!$payment) {
            return null;
        }

        $surchargeResponse = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);
        if ($surchargeResponse <= 0) {
            return null;
        }

        $orderMerchandiseTotal = (float)$order->getGrandTotal() - $surchargeResponse;
        if ($orderMerchandiseTotal <= 0) {
            return null;
        }

        $invoiceMerchandiseTotal = (float)$invoice->getSubtotal() + (float)$invoice->getShippingAmount();
        if ($invoiceMerchandiseTotal <= 0) {
            return null;
        }

        $proportionalSurcharge = round($invoiceMerchandiseTotal / $orderMerchandiseTotal * $surchargeResponse, 4);

        return $proportionalSurcharge > 0 ? $proportionalSurcharge : null;
    }

    public function initTotals(): self
    {
        $proportionalSurcharge = $this->getSurchargeAmount();
        if ($proportionalSurcharge === null) {
            return $this;
        }

        $parent = $this->getParentBlock();

        $parent->addTotalBefore(
            new DataObject([
                'code' => $this->getNameInLayout(),
                'block_name' => $this->getNameInLayout(),
            ]),
            'grand_total'
        );

        $totals = $parent->getTotals();
        if (isset($totals['grand_total'])) {
            $grandTotal = $totals['grand_total'];
            $grandTotal->setValue((float)$grandTotal->getValue() + $proportionalSurcharge);
        }

        return $this;
    }
}
