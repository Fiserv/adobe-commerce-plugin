<?php
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Lib\CommerceHub\Model\AdditionalDataCommon;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;

class AdditionalDataCommonDataBuilder implements BuilderInterface
{
	const ADDITIONAL_DATA_COMMON_KEY = 'additionalDataCommon';

	public function __construct(
		private SubjectReader $subjectReader,
		private MultiLevelLogger $logger
	) {}

	public function build(array $buildSubject): array
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();

		$isSubscription = $payment->getAdditionalInformation('is_subscription') ?? false;

		if (!$isSubscription) {
			return [];
		}

		$additionalData = new AdditionalDataCommon();
		$additionalData->setBillPaymentType('RECURRING');

		$orderDO = $paymentDO->getOrder();
		$orderIncrementId = $orderDO ? $orderDO->getOrderIncrementId() : 'N/A';
		$this->logger->logDebug(3, "Additional Data Common Data Builder:\n" . $additionalData->__toString(), "Order ID: $orderIncrementId");

		return [self::ADDITIONAL_DATA_COMMON_KEY => $additionalData];
	}
}
