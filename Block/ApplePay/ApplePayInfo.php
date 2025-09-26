<?php
namespace Fiserv\Payments\Block\ApplePay;

use Magento\Payment\Block\ConfigurableInfo;
use Magento\Framework\Phrase;

/**
 * Apple Pay Info Block
 */
class ApplePayInfo extends ConfigurableInfo
{
	/**
	 * Customize label rendering if needed
	 *
	 * @param string $field
	 * @return Phrase
	 */
	protected function getLabel($field)
	{
		return __($field);
	}

	/**
	 * Customize specific information shown in admin order view
	 *
	 * @param \Magento\Framework\DataObject|array|null $transport
	 * @return \Magento\Framework\DataObject
	 */
	protected function _prepareSpecificInformation($transport = null)
	{
		$transport = parent::_prepareSpecificInformation($transport);
		$paymentInfo = $this->getInfo();

		$applePayOrderId = $paymentInfo->getAdditionalInformation('applepay_order_id');
		$paymentSource = $paymentInfo->getAdditionalInformation('payment_source');
		$sessionId = $paymentInfo->getAdditionalInformation('payment_session');

		if ($applePayOrderId) {
			$transport->setData('Apple Pay Order ID', $applePayOrderId);
		}

		if ($paymentSource) {
			$transport->setData('Payment Source', $paymentSource);
		}

		if ($sessionId) {
			$transport->setData('Apple Pay Session ID', $sessionId);
		}

		return $transport;
	}
}