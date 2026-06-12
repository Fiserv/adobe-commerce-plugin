<?php
/**Observer that applies the surcharge amount to the Magento order's grand total before the order is placed.*/
namespace Fiserv\Payments\Observer\CommerceHub;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ApplySurchargeToOrder implements ObserverInterface
{
	const SURCHARGE_AMOUNT_KEY = 'surcharge_amount';

	/** @var MultiLevelLogger */
	private $logger;

	public function __construct(MultiLevelLogger $logger)
	{
		$this->logger = $logger;
	}

	/**
	 * Event: sales_model_service_quote_submit_before
     * Updating grand_total to ensures that the correct surcharge amount is sent to CommerceHub.
	 * @param Observer $observer
	 * @return void
	 */
	public function execute(Observer $observer)
	{
		/** @var \Magento\Sales\Model\Order $order */
		$order = $observer->getEvent()->getOrder();

		if (!$order) {
			return;
		}

		$payment = $order->getPayment();
		if (!$payment) {
			return;
		}

		$surchargeAmount = (float)$payment->getAdditionalInformation(self::SURCHARGE_AMOUNT_KEY);

		if ($surchargeAmount <= 0) {
			return;
		}


		$originalGrandTotal = (float)$order->getGrandTotal();
		$originalBaseGrandTotal = (float)$order->getBaseGrandTotal();

		$newGrandTotal = $originalGrandTotal + $surchargeAmount;
		$newBaseGrandTotal = $originalBaseGrandTotal + $surchargeAmount;

		$order->setGrandTotal($newGrandTotal);
		$order->setBaseGrandTotal($newBaseGrandTotal);

		$this->logger->logInfo(
			1,
			sprintf(
				'Surcharge of $%s applied to order %s. Grand total updated from $%s to $%s.',
				$surchargeAmount,
				$order->getIncrementId(),
				$originalGrandTotal,
				$newGrandTotal
			)
		);
	}
}

