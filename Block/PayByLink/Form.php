<?php

namespace Fiserv\Payments\Block\CommerceHub;

use Magento\Payment\Block\Form as PaymentForm;

class Form extends PaymentForm
{
	/*
	 * Template for the PayByLink form
	 *
	 * @var string
	 */
	protected $_template = 'Fiserv_Payments::form/paybylink.phtml';
}

