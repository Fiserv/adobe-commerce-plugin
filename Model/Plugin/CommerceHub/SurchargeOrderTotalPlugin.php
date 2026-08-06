<?php
namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Sales\Model\Order\Payment;

/**
 * Ensures the surcharge returned by CommerceHub is reflected in the order grand total before the order is placed. */
class SurchargeOrderTotalPlugin
{
	/** @var MultiLevelLogger */
	private $logger;

	public function __construct(MultiLevelLogger $logger)
	{
		$this->logger = $logger;
	}

	public function afterPlace(Payment $payment, Payment $result): Payment
	{
		$surcharge = $payment->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);
		if ($surcharge <= 0) {
			return $result;
		}

		$order = $payment->getOrder();
		$baseGrandTotal = $order->getBaseGrandTotal();
		$baseAuthorized = $payment->getBaseAmountAuthorized();

		if ($baseAuthorized > 0 && $baseGrandTotal >= $baseAuthorized) {
			return $result;
		}

		$originalGrandTotal = $order->getGrandTotal();
		$newGrandTotal = $originalGrandTotal + $surcharge;
		$newBaseGrandTotal = $baseGrandTotal + $surcharge;

		$order->setGrandTotal($newGrandTotal);
		$order->setBaseGrandTotal($newBaseGrandTotal);
		$order->setTotalDue($newGrandTotal - $order->getTotalPaid() - $order->getTotalRefunded());
		$order->setBaseTotalDue($newBaseGrandTotal - $order->getBaseTotalPaid() - $order->getBaseTotalRefunded());

		$payment->setAmountAuthorized($newGrandTotal);
		$payment->setBaseAmountAuthorized($newBaseGrandTotal);

		$this->logger->logInfo(
			1,
			sprintf(
				'Surcharge of $%s applied to order %s grand total ($%s → $%s).',
				$surcharge,
				$order->getIncrementId(),
				$originalGrandTotal,
				$newGrandTotal
			)
		);

		return $result;
	}
}
