<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal;

use Fiserv\Payments\Gateway\Subject\PayPal\SubjectReader;
use Fiserv\Payments\Observer\PayPal\DataAssignObserver;
use Fiserv\Payments\Lib\PayPal\Model\PaymentSession;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Payment Data Builder
 */
class SessionSourceDataBuilder implements BuilderInterface
{
	use Formatter;

	const SESSION_SOURCE_KEY = "sessionSource";
	const PAYPAL_SESSION_SOURCE_TYPE = "PayPalSession";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @param MultiLevelLogger $logger
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
		$orderIncrementId = $orderDO->getOrderIncrementId();
		
		$sessionId = $payment->getAdditionalInformation(DataAssignObserver::SESSION_ID_KEY);
		
	$source = new PaymentSession();
	$source->setSourceType(self::PAYPAL_SESSION_SOURCE_TYPE);
	$source->setSessionId($sessionId);

	$this->logger->logDebug(3, "PayPal Session Source Data Builder:\n" . (method_exists($source, '__toString') ? $source->__toString() : json_encode($source)), "Order ID: $orderIncrementId");

	return [ self::SESSION_SOURCE_KEY => $source ];
	}
}
