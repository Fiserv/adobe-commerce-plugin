<?php

namespace Fiserv\Payments\Model\PayByLink;

use Magento\Payment\Model\Method\AbstractMethod;

class Method extends AbstractMethod
{
	/** Payment method code must match etc/payment.xml and config.xml */
	const PAYMENT_METHOD_CODE = 'fiserv_pay_by_link';

	protected $_code = self::PAYMENT_METHOD_CODE;

	/**
	 * Admin form block used when the radio button is selected
	 * (render simple text)
	 */
	protected $_formBlockType = \Fiserv\Payments\Block\CommerceHub\Pbl\Form::class;

	/**
	 * Optional info block (for order view)
	 */
	protected $_infoBlockType = \Fiserv\Payments\Block\Info\PayByLink::class;

	/** Capabilities — align with config.xml flags */
	protected $_isGateway               = false;
	protected $_canAuthorize            = false;
	protected $_canCapture              = false;
	protected $_canCapturePartial       = false;
	protected $_canRefund               = false;
	protected $_canRefundInvoicePartial = false;
	protected $_canVoid                 = false;

	/** Availability scopes */
	protected $_canUseCheckout          = false; // storefront off
	protected $_canUseInternal          = true;  // admin on
	protected $_canUseForMultishipping  = false;

	/**
	 * Optional: Restrict availability to "active" config.
	 */
	public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
	{
		return (bool)$this->_scopeConfig->isSetFlag(
			'payment/' . self::PAYMENT_METHOD_CODE . '/active',
			\Magento\Store\Model\ScopeInterface::SCOPE_STORE
		);
	}
}

