<?php

namespace Fiserv\Payments\Helper;

use Fiserv\Payments\Model\ResourceModel\FailedOrder;
use Fiserv\Payments\Model\ResourceModel\FailedTransaction;
use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;
use Fiserv\Payments\Model\ValuelinkTransaction as GiftModel;
use Fiserv\Payments\Model\FailedTransaction as TxnModel;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Validator\CommerceHub\AuthorizeResponseValidator;
use Fiserv\Payments\Gateway\Validator\CommerceHub\SaleResponseValidator;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Framework\UrlInterface;

class FailedTransactionHelper
{
	protected $logger;

	const KEY_FAILED_ORDERS = 'failed_orders';
	const KEY_SUCCESSFUL = 'successful';
	const KEY_SUCCESSFUL_TXN = 'successful_txn';
	const KEY_TXN_URL = 'transaction_url';

	const ADMIN_PANEL_LABEL = "Admin";

	private $_failedOrderResource;
	private $_failedTxnResource;
	private $_valuelinkOrderHelper;
	private $_pricingHelper;
	private $_txnRepo;
	private $_search;
	private $_urlBuilder;

	public function __construct(
		FailedOrder $failedOrderResource,
		FailedTransaction $failedTxnResource,
		ValuelinkOrderHelper $valuelinkOrderHelper,
		PricingHelper $pricingHelper,
		TransactionRepositoryInterface $txnRepo,
		SearchCriteriaBuilder $search,
		UrlInterface $urlBuilder,
		MultiLevelLogger $logger

	) {
		$this->_failedOrderResource = $failedOrderResource;
		$this->_failedTxnResource = $failedTxnResource;
		$this->_valuelinkOrderHelper = $valuelinkOrderHelper;
		$this->_pricingHelper = $pricingHelper;
		$this->_txnRepo = $txnRepo;
		$this->_search = $search;
		$this->_urlBuilder = $urlBuilder;
		$this->logger = $logger;
	}

	/**
	 * Retrieve failed orders data
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getFailedOrder($order)
	{
		$failedOrder = $order ?? throw new \Exception("Unable to locate failed orders.");
		if (isset($failedOrder[0]["real_order_id"]))
		{
			$failedOrder[0][self::KEY_SUCCESSFUL] = true;
		}
		return $failedOrder[0];
	}

	public function getFailedTransactionsForOrder($order, $page = 1, $pageSize = 20, $search = '', $approvalStatus = '', $transactionState = '', $fromDate = '', $toDate = '')
	{
		$failedOrder = $this->getFailedOrder($order);
		return $this->getFailedTransactionsByOrderIncrementId($failedOrder['order_increment_id'], $page, $pageSize, $search, $approvalStatus, $transactionState, $fromDate, $toDate);
	}

	public function getFailedTransactionsByOrderIncrementId($orderIncrementId, $page = 1, $pageSize = 20, $search = '', $approvalStatus = '', $transactionState = '', $fromDate = '', $toDate = '')
	{
		$failedTransactions = $this->_failedTxnResource->getFilteredTransactions($orderIncrementId, $page, $pageSize, $search, $approvalStatus, $transactionState, $fromDate, $toDate);

		// Set IP Address to Admin for secondary transactions to match real transactions
		foreach($failedTransactions['transactions'] as &$failedTransaction)
		{
			$failedTransaction[self::KEY_TXN_URL] = $this->_urlBuilder->getUrl('fiserv/declines/transaction', ['transaction_id' => $failedTransaction['transaction_id']]);
			if ($failedTransaction[TxnModel::KEY_PAYMENT_ACTION] != SaleResponseValidator::PAYMENT_ACTION && $failedTransaction[TxnModel::KEY_PAYMENT_ACTION] != AuthorizeResponseValidator::PAYMENT_ACTION)
			{
				$failedTransaction[TxnModel::KEY_REMOTE_IP] = self::ADMIN_PANEL_LABEL;
			}
		}
		
		$giftTxns = $this->_valuelinkOrderHelper->getValuelinkTransactionsByOrderIncrementId($orderIncrementId);

		foreach ($giftTxns as $giftTxn) {
			$successTxn = $this->convertGiftTxnToFailedTxnArray($giftTxn);

			$matchesApprovalStatus = !$approvalStatus || $successTxn['approval_status'] === $approvalStatus;
			$matchesTransactionState = !$transactionState || $successTxn['transaction_state'] === $transactionState;
			$matchesSearch = !$search || !empty(array_filter(
				$successTxn,
				fn($v) => is_scalar($v) && stripos((string)$v, trim($search)) !== false
			));

			$txnDate = isset($successTxn['date_time']) ? new \DateTime($successTxn['date_time']) : null;
			$fromDateObj = $fromDate ? new \DateTime($fromDate) : null;
			$toDateObj = $toDate ? new \DateTime($toDate) : null;

			$matchesFromDate = !$fromDateObj || ($txnDate && $txnDate >= $fromDateObj);
			$matchesToDate = !$toDateObj || ($txnDate && $txnDate <= $toDateObj);

			if ($matchesApprovalStatus && $matchesTransactionState && $matchesSearch && $matchesFromDate && $matchesToDate) {
				$failedTransactions['count']++;
				$failedTransactions['transactions'][] = $successTxn;
			}
		}

		// if there's a order id, this is not actually a failed order, but a successful order with a failed txn
		if (isset($failedOrder["real_order_id"]))
		{
			$txns = $this->getTxnsOnSuccessfulOrder($failedOrder["real_order_id"], $page, $pageSize);

			foreach($txns as $txn)
			{
				$successTxn = $this->convertSuccessfulTxnToFailedTxnArray($txn, $orderIncrementId);

				$matchesApprovalStatus = !$approvalStatus || $successTxn['approval_status'] === $approvalStatus;
				$matchesTransactionState = !$transactionState || $successTxn['transaction_state'] === $transactionState;
				$matchesSearch = !$search || !empty(array_filter(
					$successTxn,
					fn($v) => is_scalar($v) && stripos((string)$v, trim($search)) !== false
				));

				$txnDate = isset($successTxn['date_time']) ? new \DateTime($successTxn['date_time']) : null;
				$fromDateObj = $fromDate ? new \DateTime($fromDate) : null;
				$toDateObj = $toDate ? new \DateTime($toDate) : null;

				$matchesFromDate = !$fromDateObj || ($txnDate && $txnDate >= $fromDateObj);
				$matchesToDate = !$toDateObj || ($txnDate && $txnDate <= $toDateObj);

				if ($matchesApprovalStatus && $matchesTransactionState && $matchesSearch && $matchesFromDate && $matchesToDate) {
					$failedTransactions['count']++;
					$failedTransactions['transactions'][] = $successTxn;
				}
			}
		}

		usort($failedTransactions['transactions'], function($a, $b) {
			return strtotime($a[TxnModel::KEY_DATE_TIME]) <=> strtotime($b[TxnModel::KEY_DATE_TIME]);
		});

		$failedTransactions['transactions'] = array_slice($failedTransactions['transactions'], (($page - 1) * $pageSize), $pageSize);

		return $failedTransactions;
	}

	public function getAppliedGiftCardAmount($order)
	{
		$failedOrder = $this->getFailedOrder($order);
		$giftTxns = $this->_valuelinkOrderHelper->getPrimaryValuelinkTransactionsByOrderIncrementId($failedOrder["order_increment_id"]);
		$giftTotal = 0;
		foreach ($giftTxns as $giftTxn)
		{
			$giftTotal += $giftTxn[GiftModel::KEY_AMOUNT];
		}

		return $giftTotal;
	}

	private function convertGiftTxnToFailedTxnArray(Array $giftTxn)
	{
		$fTxn = array();
		$fTxn[TxnModel::KEY_DATE_TIME] = $this->extractTimestampFromGiftTxn($giftTxn);
		$fTxn[TxnModel::KEY_ORDER_INCREMENT_ID] = $giftTxn[GiftModel::KEY_ORDER_INCREMENT_ID];
		$fTxn[TxnModel::KEY_TRANSACTION_STATE] = $giftTxn[GiftModel::KEY_TRANSACTION_STATE] . " (GIFT)";
		$fTxn[TxnModel::KEY_APPROVAL_STATUS] = $this->extractApprovalStatusFromGiftTxn($giftTxn) . " (GIFT)";
		$fTxn[TxnModel::KEY_TOTAL_AMOUNT] = $giftTxn[GiftModel::KEY_AMOUNT];
		$fTxn[TxnModel::KEY_REMOTE_IP] = "N/A";
		$fTxn[TxnModel::KEY_TRANSACTION_ID] = "N/A";
		$fTxn[self::KEY_TXN_URL] = '';

		return $fTxn;
	}

	private function extractApprovalStatusFromGiftTxn($giftTxn)
	{
		$path = ["paymentReceipt", "processorResponseDetails"];
		$rawResponse = $giftTxn[GiftModel::KEY_CH_RESPONSE];
		return SubjectReader::getValueSafely(json_decode($rawResponse, true), "approvalStatus", $path) ?? $giftTxn[GiftModel::KEY_TRANSACTION_STATE]; 
	}	
	
	private function extractTimestampFromGiftTxn($giftTxn)
	{
		$path = ["gatewayResponse", "transactionProcessingDetails"];
		$rawResponse = $giftTxn[GiftModel::KEY_CH_RESPONSE];
		$dateString = SubjectReader::getValueSafely(json_decode($rawResponse, true), "transactionTimestamp", $path) ?? $giftTxn[GiftModel::KEY_DATE_CREATED]; 
		$date = new \DateTime($dateString, new \DateTimeZone('UTC'));
		return $date->format('Y-m-d H:i:s');
	}

	private function convertSuccessfulTxnToFailedTxnArray(\Magento\Sales\Model\Order\Payment\Transaction $txn, string $orderIncrementId)
	{
		$fTxn = array();
		$fTxn[TxnModel::KEY_DATE_TIME] = $txn["created_at"];
		$fTxn[TxnModel::KEY_ORDER_INCREMENT_ID] = $orderIncrementId;
		$fTxn[TxnModel::KEY_APPROVAL_STATUS] = $txn["txn_type"];
		$fTxn[TxnModel::KEY_TRANSACTION_STATE] = $txn["is_closed"] == true ? "CLOSED" : "OPEN";
		$fTxn[TxnModel::KEY_TOTAL_AMOUNT] = $txn->getAdditionalInformation("amount");
		$fTxn[TxnModel::KEY_REMOTE_IP] = $this->getRealTxnIp($txn);
		$fTxn[TxnModel::KEY_TRANSACTION_ID] = $txn["txn_id"];
		$fTxn[TxnModel::KEY_PAYMENT_ACTION] = $txn->getTxnType();
		$fTxn[self::KEY_SUCCESSFUL_TXN] = $txn["transaction_id"];
		$fTxn[self::KEY_SUCCESSFUL] = true;
		$fTxn[self::KEY_TXN_URL] = $this->_urlBuilder->getUrl('sales/transactions/view', ['txn_id' =>  $txn["txn_id"]]);

		return $fTxn;
	}

	private function getRealTxnIp(\Magento\Sales\Model\Order\Payment\Transaction $txn)
	{
		$txnType = $txn->getTxnType();
		switch($txnType)
		{
			case TransactionInterface::TYPE_AUTH:
				return $this->getPrimaryTxnIp($txn);
			case TransactionInterface::TYPE_CAPTURE:
				return $this->isCaptureSale($txn) ? $this->getPrimaryTxnIp($txn) : self::ADMIN_PANEL_LABEL;
			default:
				return self::ADMIN_PANEL_LABEL;
		}
	}

	private function getPrimaryTxnIp($txn)
	{
		return !is_null($txn->getOrder()->getData("remote_ip")) ? $txn->getOrder()->getData("remote_ip") : self::ADMIN_PANEL_LABEL;
	}

	private function isCaptureSale($txn)
	{
		$auth = $txn->getOrder()->getPayment()->getBaseAmountAuthorized();
		return is_null($auth) || $auth < 0.01;
	}

	private function getTxnsOnSuccessfulOrder($orderId, $page, $pageSize)
	{
		$criteria = $this->_search
			->addFilter('order_id', $orderId)
			->setPageSize($page * $pageSize)
			->setCurrentPage(1)
			->create();

		return $this->_txnRepo->getList($criteria)->getItems();
	}

	public function formatPrice($amount)
	{
		return $this->_pricingHelper->currency($amount, true, false);
	}
}
