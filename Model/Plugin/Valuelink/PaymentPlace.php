<?php

namespace Fiserv\Payments\Model\Plugin\Valuelink;

use Fiserv\Payments\Model\Service\Valuelink\ValuelinkTransactionManager;
use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;
use Fiserv\Payments\Model\ValuelinkTransaction;
use Magento\Sales\Model\Order\Payment;
use Fiserv\Payments\Logger\MultiLevelLogger;

class PaymentPlace
{
	protected $eventManager;
	protected $logger;
	protected $orderHelper;
	protected $valuelinkTransactionManager;

	public function __construct(
		\Magento\Framework\Event\ManagerInterface $eventManager,
		MultiLevelLogger $logger,
		ValuelinkOrderHelper $orderHelper,
		ValuelinkTransactionManager $valuelinkTransactionManager
	) {
		$this->eventManager = $eventManager;
		$this->logger = $logger;
		$this->orderHelper = $orderHelper;
		$this->valuelinkTransactionManager = $valuelinkTransactionManager;
	}

	public function aroundPlace(Payment $payment, callable $proceed)
	{
		try
		{
			$result = $proceed();
			return $result;
		}
		catch (\Exception $e)
		{
			$valuelinkTransactions = $this->orderHelper->getValuelinkTransactions($payment->getOrder());

			if (count($valuelinkTransactions))
			{
				$this->logger->logInfo(1, "Exception occured while processing payment method. Reversing Valuelink transactions. Total Valuelink transactions: " . count($valuelinkTransactions));
	
				// Array to hold the failed transactions
				$failedTransactions = [];

				foreach ($valuelinkTransactions as $transaction) {
					try
					{
						$transactionId = $transaction[ValuelinkTransaction::KEY_TRANSACTION_ID];
						$this->logger->logInfo(1, "Attempting to cancel ValueLink transaction ID: " . $transactionId);
	
						$this->valuelinkTransactionManager->cancelValuelinkTransaction($payment->getOrder(), $transaction);
						$this->logger->logInfo(1, "Successfully canceled ValueLink transaction ID: " . $transactionId);
					}
					catch (\Exception $e)
					{
						// Add the failed transaction to the array
						array_push($failedTransactions, $transaction);
						$this->logger->logError(1, "Could not cancel the gift card for transaction ID: " . $transaction[ValuelinkTransaction::KEY_TRANSACTION_ID]);
					}
				}

				// Log the whole list of failed transactions
				if (!empty($failedTransactions)) {
					$failedTransactionIds = array_map(function($txn) { return $txn[ValuelinkTransaction::KEY_TRANSACTION_ID];
						}, $failedTransactions);
					$this->logger->logError(1, "Failed to cancel the following transactions: " . implode(', ', $failedTransactionIds));
				} else {
					$this->logger->logInfo(1, "All ValueLink transactions were successfully canceled.");
				}
			}
			throw $e;
		}
	}
}
