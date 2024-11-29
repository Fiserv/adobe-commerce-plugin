<?php

namespace Fiserv\Payments\Model;

use Fiserv\Payments\Api\Data\Valuelink\ValuelinkTransactionInterface;

use Magento\Framework\Model\AbstractModel;

class ValuelinkTransaction extends AbstractModel implements ValuelinkTransactionInterface
{
	public const KEY_ENTITY_ID = "entity_id";
	public const KEY_ORDER_ID = "order_id";
	public const KEY_PARENT_TRANSACTION_ID = "parent_transaction_id";
	public const KEY_INVOICE_ID = "invoice_id";
	public const KEY_CREDITMEMO_ID = "creditmemo_id";
	public const KEY_ORDER_INCREMENT_ID = "order_increment_id";
	public const KEY_TRANSACTION_ID = "transaction_id";
	public const KEY_TRANSACTION_TYPE = "transaction_type";
	public const KEY_TRANSACTION_STATE = "transaction_state";
	public const KEY_CH_REQUEST = "ch_request";
	public const KEY_CH_RESPONSE = "ch_response";
	public const KEY_AMOUNT = "amount";
	public const KEY_CURRENCY = "currency";
	public const KEY_DATE_CREATED = "date_created";

	public const AUTHORIZED_STATE = "AUTHORIZED";
	public const CAPTURED_STATE = "CAPTURED";
	public const DECLINED_STATE = "DECLINED";
	public const VOIDED_STATE = "VOIDED";

	public const AUTHORIZE_TYPE = "AUTHORIZE";
	public const CAPTURE_TYPE = "CAPTURE";
	public const SALE_TYPE = "SALE";
	public const CANCEL_TYPE = "CANCEL";

	protected function _construct()
	{
		$this->_init(\Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction::class);
	}

	public function getEntityId()
	{
		return $this->getData(self::KEY_ENTITY_ID);
	}
	
	public function setEntityId($id)
	{
		return $this->setData(self::KEY_ENTITY_ID, $id);
	}	
	
	public function getOrderId()
	{
		return $this->getData(self::KEY_ORDER_ID);
	}
	
	public function setOrderId($orderId)
	{
		return $this->setData(self::KEY_ORDER_ID, $orderId);
	}

	public function getParentTransactionId()
	{
		return $this->getData(self::KEY_PARENT_TRANSACTION_ID);
	}
	
	public function setParentTransactionId($parentTransactionId)
	{
		return $this->setData(self::KEY_PARENT_TRANSACTION_ID, $parentTransactionId);
	}

	public function getInvoiceId()
	{
		return $this->getData(self::KEY_INVOICE_ID);
	}
	
	public function setInvoiceId($invoiceId)
	{
		return $this->setData(self::KEY_INVOICE_ID, $invoiceId);
	}

	public function getCreditMemoId()
	{
		return $this->getData(self::KEY_CREDITMEMO_ID);
	}
	
	public function setCreditMemoId($creditMemoId)
	{
		return $this->setData(self::KEY_CREDITMEMO_ID, $creditMemoId);
	}

	public function getOrderIncrementId()
	{
		return $this->getData(self::KEY_ORDER_INCREMENT_ID);
	}
	
	public function setOrderIncrementId($orderIncrementId)
	{
		return $this->setData(self::KEY_ORDER_INCREMENT_ID, $orderIncrementId);
	}

	public function getTransactionId()
	{
		return $this->getData(self::KEY_TRANSACTION_ID);
	}
	
	public function setTransactionId($transactionId)
	{
		return $this->setData(self::KEY_TRANSACTION_ID, $transactionId);
	}

	public function getTransactionType()
	{
		return $this->getData(self::KEY_TRANSACTION_TYPE);
	}
	
	public function setTransactionType($transactionType)
	{
		return $this->setData(self::KEY_TRANSACTION_TYPE, $transactionType);
	}

	public function getTransactionState()
	{
		return $this->getData(self::KEY_TRANSACTION_STATE);
	}
	
	public function setTransactionState($transactionState)
	{
		return $this->setData(self::KEY_TRANSACTION_STATE, $transactionState);
	}

	public function getChRequest()
	{
		return $this->getData(self::KEY_CH_REQUEST);
	}
	
	public function setChRequest($chRequest)
	{
		return $this->setData(self::KEY_CH_REQUEST, $chRequest);
	}	
		
	public function getChResponse()
	{
		return $this->getData(self::KEY_CH_RESPONSE);
	}
	
	public function setChResponse($chResponse)
	{
		return $this->setData(self::KEY_CH_RESPONSE, $chResponse);
	}

	public function getAmount()
	{
		return $this->getData(self::KEY_AMOUNT);
	}
	
	public function setAmount($amount)
	{
		return $this->setData(self::KEY_AMOUNT, $amount);
	}

	public function getCurrency()
	{
		return $this->getData(self::KEY_CURRENCY);
	}
	
	public function setCurrency($currency)
	{
		return $this->setData(self::KEY_CURRENCY, $currency);
	}
	
	public function getDateCreated()
	{
		return $this->getData(self::KEY_DATE_CREATED);
	}
	
	public function setDateCreated($date)
	{
		return $this->setData(self::KEY_DATE_CREATED, $date);
	}

	public function convertToTxnArray()
	{
		$arr = array();

		$arr[self::KEY_ENTITY_ID] = $this->getEntityId();
		$arr[self::KEY_ORDER_ID] = $this->getOrderId();
		$arr[self::KEY_PARENT_TRANSACTION_ID] = $this->getParentTransactionId();
		$arr[self::KEY_INVOICE_ID] = $this->getInvoiceId();
		$arr[self::KEY_CREDITMEMO_ID] = $this->getCreditMemoId();
		$arr[self::KEY_ORDER_INCREMENT_ID] = $this->getOrderIncrementId();
		$arr[self::KEY_TRANSACTION_ID] = $this->getTransactionId();
		$arr[self::KEY_TRANSACTION_TYPE] = $this->getTransactionType();
		$arr[self::KEY_CH_REQUEST] = $this->getChRequest();
		$arr[self::KEY_CH_RESPONSE] = $this->getChResponse();
		$arr[self::KEY_AMOUNT] = $this->getAmount();
		$arr[self::KEY_CURRENCY] = $this->getCurrency();
		$arr[self::KEY_DATE_CREATED] = $this->getDateCreated();

		return $arr;
	}
}
