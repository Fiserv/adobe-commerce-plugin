<?php
/** Block that renders the Fiserv surcharge as a line item in the storefront */
namespace Fiserv\Payments\Block\Sales\Order;

use Magento\Framework\View\Element\Template;

class Surcharge extends Template
{
	use SurchargeBlockTrait;

	/** Retrieve the parent Totals block's order object. */
	public function getOrder()
	{
		return $this->getParentBlock()->getOrder();
	}

	public function getLabelProperties()
	{
		return $this->getParentBlock()->getLabelProperties();
	}

	public function getValueProperties()
	{
		return $this->getParentBlock()->getValueProperties();
	}
}
