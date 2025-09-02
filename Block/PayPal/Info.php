<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Block\PayPal;

use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Gateway\ConfigInterface;
use Fiserv\Payments\Gateway\Config\PayPal\Config;

/**
 * Class Info
 */

class Info extends ConfigurableInfo
{
	public function __construct(
		Context $context,
		ConfigInterface $config,
		array $data = []
	) {
		parent::__construct($context, $config, $data);
	}


	protected function _prepareSpecificInformation($transport = null)
	{
		$transport = parent::_prepareSpecificInformation($transport);
		$paymentInfo = $this->getInfo();

		if ($paypalOrderId = $paymentInfo->getAdditionalInformation('paypal_order_id')) {
			$this->setDataToTransfer($transport, __('PayPal Order ID'), $paypalOrderId);
		}
		if ($payerEmail = $paymentInfo->getAdditionalInformation('paypal_payer_email')) {
			$this->setDataToTransfer($transport, __('Payer Email'), $payerEmail);
		}
		if ($transactionId = $paymentInfo->getAdditionalInformation('paypal_transaction_id')) {
			$this->setDataToTransfer($transport, __('Transaction ID'), $transactionId);
		}

		return $transport;
	}
}
