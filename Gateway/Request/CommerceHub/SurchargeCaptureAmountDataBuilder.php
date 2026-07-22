<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\Amount;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Overrides the amount built by AmountDataBuilder with a surcharge-inclusive total when a surcharge exists.
 * Returns an empty array when there is no surcharge, leaving AmountDataBuilder's result intact.
 */
class SurchargeCaptureAmountDataBuilder implements BuilderInterface
{
	const AMOUNT_KEY = 'amount';

	/** @var SubjectReader */
	private $subjectReader;

	/** @var MultiLevelLogger */
	private $logger;

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
		$orderDO = $paymentDO->getOrder();
		$payment = $paymentDO->getPayment();
		$rawTotal = $this->subjectReader->readAmount($buildSubject);

		$surcharge = (float)$payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);
		if ($surcharge <= 0) {
			return [];
		}

		// Apply the surcharge proportionally to $rawTotal so partial captures
		// carry the correct surcharge distribution (surcharge / base order total).
		$orderGrandTotal = (float)$payment->getOrder()->getGrandTotal();
		$baseOrderTotal = $orderGrandTotal - $surcharge;
		$surchargeRate = $baseOrderTotal > 0 ? $surcharge / $baseOrderTotal : 0;
		$captureTotal = round($rawTotal * (1 + $surchargeRate), 2, PHP_ROUND_HALF_UP);

		$amt = new Amount();
		$amt->setTotal($captureTotal);
		$amt->setCurrency($orderDO->getCurrencyCode());
		$this->logger->logDebug(3, "Surcharge Capture Amount Data Builder:\n" . $amt->__toString(), "Order ID: " . $orderDO->getOrderIncrementId());

		return [self::AMOUNT_KEY => $amt];
	}
}