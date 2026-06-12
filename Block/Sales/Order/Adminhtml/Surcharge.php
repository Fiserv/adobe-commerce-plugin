<?php
/**
 * Admin surcharge totals block.
 *
 * Must extend \Magento\Sales\Block\Adminhtml\Order\Totals\Item so that
 * Magento\Sales\Block\Adminhtml\Order\Totals::_initTotals() recognises it
 * and calls initTotals() on it
 */
namespace Fiserv\Payments\Block\Sales\Order\Adminhtml;

class Surcharge extends \Magento\Sales\Block\Adminhtml\Order\Totals\Item
{
	const SURCHARGE_AMOUNT_KEY = 'surcharge_amount';

	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Magento\Framework\Registry $registry,
		\Magento\Sales\Helper\Admin $adminHelper,
		array $data = []
	) {
		parent::__construct($context, $registry, $adminHelper, $data);
	}

	/**
	 * Get the order from the parent Order\Totals block.
	 *
	 * @return \Magento\Sales\Model\Order
	 */
	public function getOrder()
	{
		return $this->getParentBlock()->getOrder();
	}

	/**
	 * Returns the surcharge amount from the payment record's additional_information,
	 * or null if no surcharge was applied.
	 * Reads from sales_order_payment
	 *
	 * @return float|null
	 */
	public function getSurchargeAmount()
	{
		$amount = $this->getOrder()->getPayment()->getAdditionalInformation(self::SURCHARGE_AMOUNT_KEY);
		if ((float)$amount <= 0) {
			return null;
		}
		return (float)$amount;
	}

	/**
	 * Register this block as a totals row before Grand Total inside
	 * class="admin__page-section-item order-totals".
	 *
	 * Overrides Item::initTotals() so we only add the row when a
	 * non-zero surcharge actually exists on the order.
	 *
	 * @return $this
	 */
	public function initTotals()
	{
		if ($this->getSurchargeAmount() === null) {
			return $this;
		}

		$total = new \Magento\Framework\DataObject(
			[
				'code' => $this->getNameInLayout(),
				'block_name' => $this->getNameInLayout(),
			]
		);

		$this->getParentBlock()->addTotalBefore($total, 'grand_total');

		return $this;
	}
}
