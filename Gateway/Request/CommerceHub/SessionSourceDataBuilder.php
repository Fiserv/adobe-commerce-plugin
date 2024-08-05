<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Observer\CommerceHub\DataAssignObserver;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentSession;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;

/**
 * Payment Data Builder
 */
class SessionSourceDataBuilder implements BuilderInterface
{
	use Formatter;

	const SESSION_SOURCE_KEY = "sessionSource";
	const PAYMENT_SESSION_SOURCE_TYPE = "PaymentSession";

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(SubjectReader $subjectReader)
	{
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
		
		$sessionId = $payment->getAdditionalInformation(DataAssignObserver::SESSION_ID_KEY);
		
		$source = new PaymentSession();
		$source->setSourceType(self::PAYMENT_SESSION_SOURCE_TYPE);
		$source->setSessionId($sessionId);

		return [ self::SESSION_SOURCE_KEY => $source ];
	}
}
