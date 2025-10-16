<?php

namespace Fiserv\Payments\Model\Plugin\Valuelink;

use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;
use Fiserv\Payments\Model\Valuelink\Helper\Invoice\ValuelinkInvoiceHelper;
use Fiserv\Payments\Model\Service\Valuelink\ValuelinkTransactionManager;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\ValuelinkTransaction;
use Magento\Sales\Model\Order\Payment;
use Magento\Framework\Exception\LocalizedException;
use Fiserv\Payments\Gateway\Config\Valuelink\Config; // Import Config class

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
	protected $config; // Add Config property

	public function __construct(
		ValuelinkOrderHelper $orderHelper,
		ValuelinkInvoiceHelper $invoiceHelper,
		MultiLevelLogger $logger,
		ValuelinkTransactionManager $valuelinkTransactionManager,
		Config $config // Inject Config class
	) {
		$this->orderHelper = $orderHelper;
		$this->invoiceHelper = $invoiceHelper;
		$this->logger = $logger;
		$this->valuelinkTransactionManager = $valuelinkTransactionManager;
		$this->config = $config; // Assign Config to property
	}

	public function aroundRegister(\Magento\Sales\Model\Order\Invoice $subject, callable $proceed)
	{
		$order = $subject->getOrder();
		if (count($this->orderHelper->getValuelinkTransactions($order)) < 1)
		{
			return $proceed();
		}
		
		$remainingGiftAuth = $this->orderHelper->getRemainingGiftAuthAmount($order);
		$uninvoicedSaleAmt = $this->orderHelper->getUninvoicedSaleAmount($order);

		$payment = $order->getPayment();

		// Fetch the selected gift card tier
		$giftCardTier = $this->config->getGiftCardTier(); // Store-specific scope can be passed here if required

		// Log the selected tier (optional, for debugging)
		$this->logger->logInfo(1, 'Selected Gift Solutions Tier in InvoiceRegister: ' . $giftCardTier);

		// Check if the invoice is a partial capture, Valuelink tier is set to Basic, and there is remaining gift auth
		if (!$this->invoiceHelper->isFullInvoice($order, $subject) && $giftCardTier == 'basic' && $remainingGiftAuth > 0) {
			throw new LocalizedException(__('Unable to carry out Partial Capture on Basic Tier of Gift Solutions'));
		}

		// if Grand Total is zero and there is remaining auth, then set Capture Case to offline
		// so we don't trigger the payment gateway capture command.
		if (($remainingGiftAuth > 0 || $uninvoicedSaleAmt > 0) && $subject->getGrandTotal() < 0.01)
		{
			$subject->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
		}
	
		$captures = array();
		try
		{
			$valuelinkInvoiceAmount = $this->invoiceHelper->getValuelinkInvoiceAmount($order, $subject, $remainingGiftAuth);

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
