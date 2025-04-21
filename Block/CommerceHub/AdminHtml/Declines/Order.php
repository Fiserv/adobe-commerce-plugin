<?php

namespace Fiserv\Payments\Block\CommerceHub\AdminHtml\Declines;

use Fiserv\Payments\Model\ResourceModel\FailedOrder; 
use Fiserv\Payments\Model\ResourceModel\FailedTransaction; 
use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;
use Fiserv\Payments\Model\ValuelinkTransaction as GiftModel;
use Fiserv\Payments\Model\FailedTransaction as TxnModel;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class Order extends \Magento\Backend\Block\Template
{
	const KEY_FAILED_ORDERS = 'failed_orders';
	const KEY_SUCCESSFUL = 'successful';
	const KEY_SUCCESSFUL_TXN = 'successful_txn';

	private $_failedOrderResource;
	private $_failedTxnResource;
	private $_valuelinkOrderHelper;
	private $_pricingHelper;
	private $_txnRepo;
	private $_search;

	public function __construct(
		\Magento\Backend\Block\Template\Context $context,
		FailedOrder $failedOrderResource,
		FailedTransaction $failedTxnResource,
		ValuelinkOrderHelper $valuelinkOrderHelper,
		PricingHelper $pricingHelper,
		TransactionRepositoryInterface $txnRepo,
		SearchCriteriaBuilder $search,
		array $data = []
	) {
		$this->_failedOrderResource = $failedOrderResource;
		$this->_failedTxnResource = $failedTxnResource;
		$this->_valuelinkOrderHelper = $valuelinkOrderHelper;
		$this->_pricingHelper = $pricingHelper;
		$this->_txnRepo = $txnRepo;
		$this->_search = $search;
		parent::__construct($context, $data);
	}


	/**
	 * Retrieve failed orders data
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getFailedOrder()
	{
		$failedOrder = $this->getData(self::KEY_FAILED_ORDERS) ?? throw new \Exception("Unable to locate failed orders.");
		if (isset($failedOrder[0]["real_order_id"]))
		{
			$failedOrder[0][self::KEY_SUCCESSFUL] = true;
		}

		return $failedOrder[0];
	}

	public function getFailedTransactionsForOrder()
	{
		$failedOrder = $this->getFailedOrder();
		$failedTransactions = $this->_failedTxnResource->getByOrderIncrementId($failedOrder["order_increment_id"]);
		$giftTxns = $this->_valuelinkOrderHelper->getValuelinkTransactionsByOrderIncrementId($failedOrder["order_increment_id"]);
		
		foreach($giftTxns as $giftTxn)
		{
			array_push($failedTransactions, $this->convertGiftTxnToFailedTxnArray($giftTxn));
		}


		// if there's a order id, this is not actually a failed order, but a successful order with a failed txn
		if (isset($failedOrder["real_order_id"]))
		{
			$txns = $this->getTxnsOnSuccessfulOrder($failedOrder["real_order_id"]);

			foreach($txns as $txn)
			{
				array_push($failedTransactions, $this->convertSuccessfulTxnToFailedTxnArray($txn, $failedOrder["order_increment_id"]));
			}			
		}

		usort($failedTransactions, function($a, $b) {
			return strtotime($a[TxnModel::KEY_DATE_TIME]) <=> strtotime($b[TxnModel::KEY_DATE_TIME]);
		});
		return $failedTransactions;
	}

	public function getAppliedGiftCardAmount()
	{
		$failedOrder = $this->getFailedOrder();
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
		$fTxn[TxnModel::KEY_TOTAL_AMOUNT] = $txn->getOrder()->getBaseAmountOrdered();
		$fTxn[TxnModel::KEY_REMOTE_IP] = "N/A";
		$fTxn[TxnModel::KEY_TRANSACTION_ID] = $txn["txn_id"];
		$fTxn[self::KEY_SUCCESSFUL_TXN] = $txn["transaction_id"];
		$fTxn[self::KEY_SUCCESSFUL] = true;

		return $fTxn;
	}

	private function getTxnsOnSuccessfulOrder($orderId)
	{
		$criteria = $this->_search
			->addFilter('order_id', $orderId)
			->create();

		return $this->_txnRepo->getList($criteria)->getItems();
	}

	public function formatPrice($amount)
	{
		return $this->_pricingHelper->currency($amount, true, false);
	}
}
