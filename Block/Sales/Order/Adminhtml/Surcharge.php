<?php
/** Block that renders the Fiserv surcharge as a line item in the Admin order totals */
namespace Fiserv\Payments\Block\Sales\Order\Adminhtml;

use Fiserv\Payments\Block\Sales\Order\SurchargeBlockTrait;

class Surcharge extends \Magento\Sales\Block\Adminhtml\Order\Totals\Item
{
	use SurchargeBlockTrait;
}
