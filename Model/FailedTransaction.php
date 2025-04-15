<?php

namespace Fiserv\Payments\Model;

use Fiserv\Payments\Api\Data\FailedTransaction\FailedTransactionInterface;
use Magento\Framework\Model\AbstractModel;

class FailedTransaction extends AbstractModel implements FailedTransactionInterface
{
	public const KEY_ENTITY_ID = "entity_id";
	public const KEY_ORDER_INCREMENT_ID = "order_increment_id";
	public const KEY_DATE_TIME = "date_time";
	public const KEY_TRANSACTION_STATE = "transaction_state";
	public const KEY_APPROVAL_STATUS = "approval_status";
	public const KEY_TOTAL_AMOUNT = "total_amount";
	public const KEY_REMOTE_IP = "remote_ip";
	public const KEY_PROCESSOR = "processor";
	public const KEY_HOST = "host";
	public const KEY_EXPIRATION_MONTH = "expiration_month";
	public const KEY_EXPIRATION_YEAR = "expiration_year";
	public const KEY_LAST4 = "last4";
	public const KEY_SCHEME = "scheme";
	public const KEY_NETWORK_RESPONSE_CODE = "network_response_code";
	public const KEY_BANK_ASSOCIATION_DETAILS = "bank_association_details";
	public const KEY_BIN = "bin";
	public const KEY_TRANSACTION_ID = "transaction_id";
	public const KEY_CURRENCY = "currency";
	public const KEY_COUNTRY = 'country';
	public const KEY_API_TRACE_ID = 'api_trace_id';
	public const KEY_HOST_RESPONSE_MESSAGE = 'host_response_message';
	public const KEY_RETRIEVAL_REFERENCE_NUMBER = 'retrieval_reference_number';
	public const KEY_RESPONSE_CODE = 'responseCode';
	public const KEY_RESPONSE_MESSAGE = 'responseMessage';
	public const KEY_MERCHANT_ADVICE_CODE = 'merchantAdviceCode';
	public const KEY_SECURITY_CODE_MATCH = 'securityCodeMatch';


	protected function _construct()
	{
		$this->_init(\Fiserv\Payments\Model\ResourceModel\FailedTransaction::class);
	}

	public function getEntityId()
	{
		return $this->getData(self::KEY_ENTITY_ID);
	}

	public function setEntityId($entityId)
	{
		return $this->setData(self::KEY_ENTITY_ID, $entityId);
	}

	public function getOrderIncrementId()
	{
		return $this->getData(self::KEY_ORDER_INCREMENT_ID);
	}

	public function setOrderIncrementId($orderIncrementId)
	{
		return $this->setData(self::KEY_ORDER_INCREMENT_ID, $orderIncrementId);
	}

	public function getDateTime()
	{
		return $this->getData(self::KEY_DATE_TIME);
	}

	public function setDateTime($dateTime)
	{
		return $this->setData(self::KEY_DATE_TIME, $dateTime);
	}

	public function getTransactionState()
	{
		return $this->getData(self::KEY_TRANSACTION_STATE);
	}

	public function setTransactionState($transactionState)
	{
		return $this->setData(self::KEY_TRANSACTION_STATE, $transactionState);
	}

	public function getApprovalStatus()
	{
		return $this->getData(self::KEY_APPROVAL_STATUS);
	}

	public function setApprovalStatus($approvalStatus)
	{
		return $this->setData(self::KEY_APPROVAL_STATUS, $approvalStatus);
	}

	public function getTotalAmount()
	{
		return $this->getData(self::KEY_TOTAL_AMOUNT);
	}

	public function setTotalAmount($totalAmount)
	{
		return $this->setData(self::KEY_TOTAL_AMOUNT, $totalAmount);
	}

	public function getRemoteIp()
	{
		return $this->getData(self::KEY_REMOTE_IP);
	}

	public function setRemoteIp($remoteIp)
	{
		return $this->setData(self::KEY_REMOTE_IP, $remoteIp);
	}

	public function getProcessor()
	{
		return $this->getData(self::KEY_PROCESSOR);
	}

	public function setProcessor($processor)
	{
		return $this->setData(self::KEY_PROCESSOR, $processor);
	}

	public function getHost()
	{
		return $this->getData(self::KEY_HOST);
	}

	public function setHost($host)
	{
		return $this->setData(self::KEY_HOST, $host);
	}

	public function getExpirationMonth()
	{
		return $this->getData(self::KEY_EXPIRATION_MONTH);
	}

	public function setExpirationMonth($expirationMonth)
	{
		return $this->setData(self::KEY_EXPIRATION_MONTH, $expirationMonth);
	}

	public function getExpirationYear()
	{
		return $this->getData(self::KEY_EXPIRATION_YEAR);
	}

	public function setExpirationYear($expirationYear)
	{
		return $this->setData(self::KEY_EXPIRATION_YEAR, $expirationYear);
	}

	public function getLast4()
	{
		return $this->getData(self::KEY_LAST4);
	}

	public function setLast4($last4)
	{
		return $this->setData(self::KEY_LAST4, $last4);
	}

	public function getScheme()
	{
		return $this->getData(self::KEY_SCHEME);
	}

	public function setScheme($scheme)
	{
		return $this->setData(self::KEY_SCHEME, $scheme);
	}

	public function getNetworkResponseCode()
	{
		return $this->getData(self::KEY_NETWORK_RESPONSE_CODE);
	}

	public function setNetworkResponseCode($networkResponseCode)
	{
		return $this->setData(self::KEY_NETWORK_RESPONSE_CODE, $networkResponseCode);
	}

	public function getBankAssociationDetails()
	{
		return $this->getData(self::KEY_BANK_ASSOCIATION_DETAILS);
	}

	public function setBankAssociationDetails($bankAssociationDetails)
	{
		return $this->setData(self::KEY_BANK_ASSOCIATION_DETAILS, $bankAssociationDetails);
	}

	public function getBin()
	{
		return $this->getData(self::KEY_BIN);
	}

	public function setBin($bin)
	{
		return $this->setData(self::KEY_BIN, $bin);
	}

	public function getTransactionId()
	{
		return $this->getData(self::KEY_TRANSACTION_ID);
	}

	public function setTransactionId($transactionId)
	{
		return $this->setData(self::KEY_TRANSACTION_ID, $transactionId);
	}

	public function getCurrency()
	{
		return $this->getData(self::KEY_CURRENCY);
	}

	public function setCurrency($currency)
	{
		return $this->setData(self::KEY_CURRENCY, $currency);
	}

	public function getCountry()
	{
		return $this->getData(self::KEY_COUNTRY);
	}

	public function setCountry($country)
	{
		return $this->setData(self::KEY_COUNTRY, $country);
	}

	public function getApiTraceId()
	{
		return $this->getData(self::KEY_API_TRACE_ID);
	}

	public function setApiTraceId($apiTraceId)
	{
		return $this->setData(self::KEY_API_TRACE_ID, $apiTraceId);
	}

	public function getHostResponseMessage()
	{
		return $this->getData(self::KEY_HOST_RESPONSE_MESSAGE);
	}

	public function setHostResponseMessage($hostResponseMessage)
	{
		return $this->setData(self::KEY_HOST_RESPONSE_MESSAGE, $hostResponseMessage);
	}

	public function getRetrievalReferenceNumber()
	{
		return $this->getData(self::KEY_HOST_RESPONSE_MESSAGE);
	}

	public function setRetrievalReferenceNumber($retrievalReferenceNumber)
	{
		return $this->setData(self::KEY_RETRIEVAL_REFERENCE_NUMBER, $retrievalReferenceNumber);
	}

	public function getResponseCode()
	{
		return $this->getData(self::KEY_RESPONSE_CODE);
	}

	public function setResponseCode($responseCode)
	{
		return $this->setData(self::KEY_RESPONSE_CODE, $responseCode);
	}

	public function getResponseMessage()
	{
		return $this->getData(self::KEY_RESPONSE_MESSAGE);
	}

	public function setResponseMessage($responseMessage)
	{
		return $this->setData(self::KEY_RESPONSE_MESSAGE, $responseMessage);
	}

	public function getMerchantAdviceCode()
	{
		return $this->getData(self::KEY_MERCHANT_ADVICE_CODE);
	}

	public function setMerchantAdviceCode($merchantAdviceCode)
	{
		return $this->setData(self::KEY_MERCHANT_ADVICE_CODE, $merchantAdviceCode);
	}
	public function getSecurityCodeMatch()
	{
		return $this->getData(self::KEY_SECURITY_CODE_MATCH);
	}

	public function setSecurityCodeMatch($securityCodeMatch)
	{
		return $this->setData(self::KEY_SECURITY_CODE_MATCH, $securityCodeMatch);
	}
}
