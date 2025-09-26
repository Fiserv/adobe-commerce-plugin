<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal;

use Fiserv\Payments\Gateway\Request\PayPal\PayPalTransactionDetailsDataBuilder;

/**
 * Payment Data Builder
 */
class RefundDetailsDataBuilder extends PayPalTransactionDetailsDataBuilder
{
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$payment->setAdditionalInformation('is_refund', true);
		$payment->save();
		return parent::build($buildSubject);
	}

	/**
	 * Get Capture Flag
	 * @return bool
	 */
	protected function getCaptureFlag()
	{
		return self::REFUND;
	}
}
