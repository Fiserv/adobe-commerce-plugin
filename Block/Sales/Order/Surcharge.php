<?php
/**
 * Block that renders the Fiserv surcharge as a line item in the storefront
 * order totals section (sales/order/view, print, invoice, credit memo, emails).
 */
namespace Fiserv\Payments\Block\Sales\Order;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Surcharge extends Template
{
	const SURCHARGE_AMOUNT_KEY = 'surcharge_amount';

	public function __construct(
		Context $context,
		array $data = []
	) {
		parent::__construct($context, $data);
	}

	/**
	 * Retrieve the parent Totals block's order object.
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
	 * Register this block as a totals item so Magento inserts it
	 * between Shipping & Handling and Grand Total.
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

	/**
	 * Proxy to parent for consistent table cell rendering.
	 *
	 * @return mixed
	 */
	public function getLabelProperties()
	{
		return $this->getParentBlock()->getLabelProperties();
	}

	/**
	 * Proxy to parent for consistent table cell rendering.
	 *
	 * @return mixed
	 */
	public function getValueProperties()
	{
		return $this->getParentBlock()->getValueProperties();
	}
}

