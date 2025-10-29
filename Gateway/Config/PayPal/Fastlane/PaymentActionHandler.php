<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\PayPal\Fastlane;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Fiserv\Payments\Gateway\Config\PayPal\Config as PayPalConfig;

class PaymentActionHandler implements ValueHandlerInterface
{
	/**
	 * @var SubjectReader
	 */
	private $subjectReader;
	
	private $paypalConfig;

	/**
	 * CanVoidHandler constructor.
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(
		PayPalConfig $paypalConfig
	) {
		$this->paypalConfig = $paypalConfig;
	}

	/**
	 * Retrieve method configured value
	 *
	 * @param array $subject
	 * @param int|null $storeId
	 *
	 * @return mixed
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function handle(array $subject, $storeId = null)
	{
		return $this->paypalConfig->getPaymentAction($storeId);
	}
}
