<?php

namespace Fiserv\Payments\Model\Plugin\Valuelink;

use Fiserv\Payments\Model\Service\Valuelink\ValuelinkTransactionManager;
use Fiserv\Payments\Model\Service\Valuelink\ValuelinkQuoteManager;
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
	protected $valuelinkQuoteManager;

	public function __construct(
		\Magento\Framework\Event\ManagerInterface $eventManager,
		MultiLevelLogger $logger,
		ValuelinkOrderHelper $orderHelper,
		ValuelinkTransactionManager $valuelinkTransactionManager,
		ValuelinkQuoteManager $valuelinkQuoteManager
	) {
		$this->eventManager = $eventManager;
		$this->logger = $logger;
		$this->orderHelper = $orderHelper;
		$this->valuelinkTransactionManager = $valuelinkTransactionManager;
		$this->valuelinkQuoteManager = $valuelinkQuoteManager;
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
			// Need to gather by increment id here since order has failed
			$valuelinkTransactions = $this->orderHelper->getValuelinkTransactionsByOrderIncrementId($payment->getOrder()->getIncrementId());
			$valuelinkTransactions = $this->orderHelper->filterCanceledValuelinkTransactions($valuelinkTransactions);

			// Check for ValueLink transactions before proceeding
			if (empty($valuelinkTransactions)) {
				throw $e;
			}

			list($captureTransactions, $authorizationTransactions) = $this->filterValuelinkTransactions($valuelinkTransactions);
			$orderTransactions = array_merge($captureTransactions, $authorizationTransactions);

			$this->logger->logInfo(1, "Reversing ValueLink transactions...");
			$failedTransactions = [];
			$this->processTransactionCancellations($orderTransactions, $payment, $failedTransactions);

			if (!empty($failedTransactions))
			{
				$failedTransactionIds = array_map(function ($txn) { return $txn[ValuelinkTransaction::KEY_TRANSACTION_ID]; }, $failedTransactions);
				$this->logger->logError(1, "Failed to cancel the following transactions: " . implode(', ', $failedTransactionIds));
			}
			else
			{
				$this->logger->logInfo(1, "All ValueLink transactions successfully canceled");
			}

			// Remove all ValueLink cards from the quote
			$quoteId = $payment->getOrder()->getQuoteId();
			$this->valuelinkQuoteManager->RemoveAllValuelinkCardsFromQuote($quoteId);
			throw $e;
		}
	}

	/**
	 * Filter transactions into captures and authorizations.
	 *
	 * @param array $transactions
	 * @return array
	 */
	private function filterValuelinkTransactions(array $transactions): array
	{
		$captures = [];
		$authorizations = [];

		foreach ($transactions as $transaction)
		{
			$transactionState = $transaction[ValuelinkTransaction::KEY_TRANSACTION_STATE];

			if ($transactionState === ValuelinkTransaction::CAPTURED_STATE)
			{
				$captures[] = $transaction;
			}
			elseif ($transactionState === ValuelinkTransaction::AUTHORIZED_STATE)
			{
				$authorizations[] = $transaction;
			}
		}

		return [$captures, $authorizations];
	}

	/**
	 * Process transaction cancellations.
	 *
	 * @param array $transactions
	 * @param Payment $payment
	 * @param array &$failedTransactions
	 * @return void
	 */
	private function processTransactionCancellations(array $transactions, Payment $payment, array &$failedTransactions): void
	{
		foreach ($transactions as $transaction)
		{
			try
			{
				$this->valuelinkTransactionManager->cancelValuelinkTransaction($payment->getOrder(), $transaction);
			}
			catch (\Exception $e)
			{
				$failedTransactions[] = $transaction;
			}
		}
	}
}

