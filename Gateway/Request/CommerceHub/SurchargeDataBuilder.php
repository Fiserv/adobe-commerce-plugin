<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Request\BuilderInterface;

class SurchargeDataBuilder implements BuilderInterface
{
	const SURCHARGE_ADJUSTED_TOTAL_KEY = 'surcharge_adjusted_total';

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
	public function build(array $buildSubject): array
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
        $rawTotal = $this->subjectReader->readAmount($buildSubject);

		// triggers when store credit or GiftCard covers the full order total.
		if ($rawTotal > 0) {
			return [];
		}

		$surchargeEstimate = (float)$payment->getAdditionalInformation('surcharge_estimate');
		$surchargeBaseAmount = (float)$payment->getAdditionalInformation('surcharge_base_amount');

		// If there is no frontend surcharge estimate, move on
		if ($surchargeEstimate <= 0 || $surchargeBaseAmount <= 0) {
			return [];
		}

		$rate = $surchargeEstimate / $surchargeBaseAmount;
		$adjustedTotal = $surchargeEstimate / (1.0 + $rate);
        $payment->setAdditionalInformation(self::SURCHARGE_ADJUSTED_TOTAL_KEY, $adjustedTotal);

		return [];
	}
}

