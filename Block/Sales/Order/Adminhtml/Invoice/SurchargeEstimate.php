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
        $invoice = $this->getInvoice();
        return $invoice ? $invoice->getOrder() : null;
    }

    public function getSurchargeAmount(): ?float
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

        $invoiced = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_INVOICED_KEY);
        $remaining = round($surcharge - $invoiced, 4);

        return $remaining > 0 ? $remaining : null;
    }

    public function initTotals(): self
    {
        $surcharge = $this->getSurchargeAmount();
        if ($surcharge === null) {
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
            $grandTotal->setValue((float)$grandTotal->getValue() + $surcharge);
        }

        return $this;
    }
}

