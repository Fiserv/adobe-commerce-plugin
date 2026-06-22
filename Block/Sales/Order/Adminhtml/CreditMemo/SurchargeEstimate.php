<?php
/**
 * Block that renders the "Estimated Surcharge Fee Refund" read-only line item
 * in the Admin credit memo totals form, positioned above Grand Total.
 *
 * The estimate is proportional: for every $1 of items being credited, the
 * corresponding share of the original surcharge is included.
 *
 * Formula: estimatedRefund = creditMemoGrandTotal * surcharge / (orderGrandTotal - surcharge)
 */
namespace Fiserv\Payments\Block\Sales\Order\Adminhtml\CreditMemo;

use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Registry;

class SurchargeEstimate extends Template
{
    /** @var Registry */
    private $registry;

    public function __construct(
        Context $context,
        Registry $registry,
        array $data = []
    ) {
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Returns the order attached to the current credit memo session, or null.
     *
     * @return \Magento\Sales\Model\Order|null
     */
    public function getOrder(): ?\Magento\Sales\Model\Order
    {
        $creditmemo = $this->getParentBlock()->getCreditmemo();
        return $creditmemo ? $creditmemo->getOrder() : null;
    }

    /**
     * Returns the current (in-memory) credit memo being constructed.
     *
     * @return \Magento\Sales\Model\Order\Creditmemo|null
     */
    public function getCreditMemo(): ?\Magento\Sales\Model\Order\Creditmemo
    {
        return $this->getParentBlock()->getCreditmemo();
    }

    /**
     * Calculates the estimated surcharge portion for this credit memo.
     *
     * Returns null when:
     *  - No surcharge was applied to the original order
     *  - The order grand total equals the surcharge (division by zero guard)
     *
     * @return float|null
     */
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
        $baseAmount = $orderGrandTotal - $surcharge;

        if ($baseAmount <= 0) {
            return null;
        }

        $creditMemo = $this->getCreditMemo();
        if (!$creditMemo) {
            return null;
        }

        // Use the credit memo grand total (items + shipping being refunded, without surcharge adjustment)
        $creditMemoTotal = (float)$creditMemo->getGrandTotal();

        // surchargePercent = surcharge / (orderTotal - surcharge)
        // estimatedRefund  = creditMemoTotal * surchargePercent
        $estimated = round($creditMemoTotal * $surcharge / $baseAmount, 4);

        return $estimated > 0 ? $estimated : null;
    }

    /**
     * Registers this block as a totals row inserted just before Grand Total.
     *
     * @return $this
     */
    public function initTotals(): self
    {
        if ($this->getEstimatedSurchargeRefund() === null) {
            return $this;
        }

        $total = new DataObject([
            'code'       => $this->getNameInLayout(),
            'block_name' => $this->getNameInLayout(),
        ]);

        $this->getParentBlock()->addTotalBefore($total, 'grand_total');

        return $this;
    }
}

