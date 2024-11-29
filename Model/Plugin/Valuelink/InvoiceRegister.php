<?php

namespace Fiserv\Payments\Model\Plugin\Valuelink;

use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;
use Fiserv\Payments\Model\Valuelink\Helper\Invoice\ValuelinkInvoiceHelper;
use Fiserv\Payments\Model\Service\Valuelink\ValuelinkTransactionManager;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\ValuelinkTransaction;
use Magento\Sales\Model\Order\Payment;
use Magento\Framework\Exception\LocalizedException;

/**
 * Plugin to capture Valuelink authorizations when
 * an invoice is created
 */
class InvoiceRegister
{
	protected $orderHelper;
	protected $invoiceHelper;
	protected $logger;
	protected $valuelinkTransactionManager;

	public function __construct(
		ValuelinkOrderHelper $orderHelper,
		ValuelinkInvoiceHelper $invoiceHelper,
		MultiLevelLogger $logger,
		ValuelinkTransactionManager $valuelinkTransactionManager
    ) {
		$this->orderHelper = $orderHelper;
		$this->invoiceHelper = $invoiceHelper;
		$this->logger = $logger;
		$this->valuelinkTransactionManager = $valuelinkTransactionManager;
    }

	public function aroundRegister(\Magento\Sales\Model\Order\Invoice $subject, callable $proceed)
	{
		$order = $subject->getOrder();
		$remainingAuth = $this->orderHelper->getRemainingAuthAmount($order);
		$uninvoicedSaleAmt = $this->orderHelper->getUninvoicedSaleAmount($order);

		// if Grand Total is zero and there is remaining auth, then set Capture Case to offline
		// so we don't trigger the payment gateway capture command.
		if (($remainingAuth > 0 || $uninvoicedSaleAmt > 0) && $subject->getGrandTotal() < 0.01)
		{
			$subject->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
		}
	
		$captures = array();
		try
		{
			$order = $subject->getOrder();
			$remainingAuth = $this->orderHelper->getRemainingAuthAmount($order);
			$valuelinkInvoiceAmount = $this->invoiceHelper->getValuelinkInvoiceAmount($order, $subject, $remainingAuth);

			// Capture command will capture uncaptured Valuelink authorizations
			// Orders being captured can contain uncaptured AND captured Valuelink authorizations.
			$txns = $this->orderHelper->filterCanceledValuelinkTransactions($this->orderHelper->getValuelinkTransactionsByOrderIncrementId($order->getIncrementId()));
			$balancedAuths = $this->orderHelper->getBalancedAuths($order, $txns);
			if (count($balancedAuths))
			{
				$authOrder = $this->invoiceHelper->getAuthCaptureSequence($valuelinkInvoiceAmount, $balancedAuths);
				$totalCaptured = 0;
				foreach ($authOrder as $auth)
				{
					$capture = $this->valuelinkTransactionManager->captureValuelinkTransaction($subject, $auth["auth"], $auth["captureAmount"], $auth["captures"], $auth["final"]);
					array_push($captures, $capture);
				}
			}
			
			// Simulate exception for testing purposes
			// throw new \Exception('Simulated exception for testing transaction reversal logic');
			$result = $proceed();
			return $result;
		} catch(\Exception $e)
		{
			if (!empty($captures)) {
				$this->logger->logInfo(1, "Reversing ValueLink transactions...");
				$failedTransactions = [];
				$this->processTransactionCancellations($captures, $order, $failedTransactions);

				if (!empty($failedTransactions)) {
					$failedTransactionIds = array_map(function ($txn) { return $txn['transaction_id']; }, $failedTransactions);
					$this->logger->logError(1,"Failed to cancel the following transactions: " . implode(', ', $failedTransactionIds));
				}
				else {
					$this->logger->logInfo(1, "All Valuelink transactions successfully canceled");
				}
			}

			throw new \Magento\Framework\Exception\LocalizedException(__("Invoice not created. An error during transaction processing: " . $e->getMessage())); // Ensure the original exception is re-thrown
		}

	}
	
	private function processTransactionCancellations(array $transactions, $order, array &$failedTransactions): void
	{
		foreach ($transactions as $transaction)
		{
			try
			{
				$this->valuelinkTransactionManager->cancelValuelinkTransaction($order, $transaction->convertToTxnArray());
			}
			catch (\Exception $e)
			{
				$this->logger->logCritical(1, "Error canceling transaction : " . $e->getMessage());
				$failedTransactions[] = $transaction;
			}
		}
	}
}
