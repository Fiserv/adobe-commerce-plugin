<?php

namespace Fiserv\Payments\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Payment\Model\Method\AbstractMethod;

/**
 * ApplePay payment method model
 */
class ApplePay extends AbstractMethod
{
	const CODE = 'fiserv_applepay';

	protected $_code = self::CODE;

	protected $_isGateway = true;

	protected $_canCapture = true;

	protected $_canCapturePartial = true;

	protected $_canRefund = true;

	protected $_canRefundInvoicePartial = true;

	protected $_canVoid = true;

	protected $_canUseInternal = false;

	protected $_canUseCheckout = true;

	protected $_canAuthorize = true;

	protected $_isInitializeNeeded = false;

	/**
	 * @param CartInterface|null $quote
	 * @return bool
	 */
	public function isAvailable(CartInterface $quote = null)
	{
		return parent::isAvailable($quote);
	}
}
