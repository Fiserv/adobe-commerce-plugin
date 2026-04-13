<?php
namespace Fiserv\Payments\Model\Subscription;

use Fiserv\Payments\Api\Data\SubscriptionOrder\SubscriptionOrderInterface;
use Magento\Framework\Model\AbstractModel;

class Order extends AbstractModel implements SubscriptionOrderInterface
{
	const STATUS_ACTIVE = 'active';
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_PROCESSED = 'processed';

	protected function _construct()
	{
		$this->_init(\Fiserv\Payments\Model\ResourceModel\Subscription\Order::class);
	}

	public function getEntityId()
	{
		return $this->getData(self::ENTITY_ID);
	}

	public function setEntityId($entityId)
	{
		return $this->setData(self::ENTITY_ID, $entityId);
	}

	public function getCustomerName()
	{
		return $this->getData(self::CUSTOMER_NAME);
	}

	public function setCustomerName($customerName)
	{
		return $this->setData(self::CUSTOMER_NAME, $customerName);
	}

	public function getOrderIncrementId()
	{
		return $this->getData(self::ORDER_INCREMENT_ID);
	}

	public function setOrderIncrementId($orderIncrementId)
	{
		return $this->setData(self::ORDER_INCREMENT_ID, $orderIncrementId);
	}

	public function getStatus()
	{
		return $this->getData(self::STATUS);
	}

	public function setStatus($status)
	{
		return $this->setData(self::STATUS, $status);
	}

	public function getIntervalValue()
	{
		return (int)$this->getData(self::INTERVAL_VALUE);
	}

	public function setIntervalValue($intervalValue)
	{
		return $this->setData(self::INTERVAL_VALUE, $intervalValue);
	}

	public function getIntervalUnit()
	{
		return $this->getData(self::INTERVAL_UNIT);
	}

	public function setIntervalUnit($intervalUnit)
	{
		return $this->setData(self::INTERVAL_UNIT, $intervalUnit);
	}

	public function getNextBillingDatetime()
	{
		return $this->getData(self::NEXT_BILLING_DATETIME);
	}

	public function setNextBillingDatetime($dateTime)
	{
		return $this->setData(self::NEXT_BILLING_DATETIME, $dateTime);
	}


	public function getSchemeReferenceTransactionId()
	{
		return $this->getData(self::SCHEME_REFERENCE_TRANSACTION_ID);
	}

	public function setSchemeReferenceTransactionId($id)
	{
		return $this->setData(self::SCHEME_REFERENCE_TRANSACTION_ID, $id);
	}

	public function getPaymentToken()
	{
		return $this->getData(self::PAYMENT_TOKEN);
	}

	public function setPaymentToken($token)
	{
		return $this->setData(self::PAYMENT_TOKEN, $token);
	}

	public function getTokenSource()
	{
		return $this->getData(self::TOKEN_SOURCE);
	}

	public function setTokenSource($source)
	{
		return $this->setData(self::TOKEN_SOURCE, $source);
	}

	public function getSequence()
	{
		return $this->getData(self::SEQUENCE);
	}

	public function setSequence($sequence)
	{
		return $this->setData(self::SEQUENCE, $sequence);
	}

	public function getFailedAttempts()
	{
		return (int)$this->getData(self::FAILED_ATTEMPTS);
	}

	public function setFailedAttempts($attempts)
	{
		return $this->setData(self::FAILED_ATTEMPTS, $attempts);
	}

	public function incrementFailedAttempts()
	{
		$current = $this->getFailedAttempts();
		return $this->setData(self::FAILED_ATTEMPTS, $current + 1);
	}

	public function resetFailedAttempts()
	{
		return $this->setData(self::FAILED_ATTEMPTS, 0);
	}

	public function getCreatedAt()
	{
		return $this->getData(self::CREATED_AT);
	}

	public function setCreatedAt($createdAt)
	{
		return $this->setData(self::CREATED_AT, $createdAt);
	}

	public function getUpdatedAt()
	{
		return $this->getData(self::UPDATED_AT);
	}

	public function setUpdatedAt($updatedAt)
	{
		return $this->setData(self::UPDATED_AT, $updatedAt);
	}

	public function getExpirationMonth()
	{
		return $this->getData(self::EXPIRATION_MONTH);
	}

	public function setExpirationMonth($month)
	{
		return $this->setData(self::EXPIRATION_MONTH, $month);
	}

	public function getExpirationYear()
	{
		return $this->getData(self::EXPIRATION_YEAR);
	}

	public function setExpirationYear($year)
	{
		return $this->setData(self::EXPIRATION_YEAR, $year);
	}

	public function getOriginalOrderIncrement()
	{
		return $this->getData(self::ORIGINAL_ORDER_INCREMENT);
	}

	public function setOriginalOrderIncrement($orderIncrement)
	{
		return $this->setData(self::ORIGINAL_ORDER_INCREMENT, $orderIncrement);
	}

	public function getCustomerId()
	{
		$val = $this->getData(self::CUSTOMER_ID);
		return $val === null ? null : (int)$val;
	}

	public function setCustomerId($customerId)
	{
		return $this->setData(self::CUSTOMER_ID, $customerId);
	}

	public function getCustomerEmail()
	{
		return $this->getData(self::CUSTOMER_EMAIL);
	}

	public function setCustomerEmail($email)
	{
		return $this->setData(self::CUSTOMER_EMAIL, $email);
	}

	public function getLastGatewayTransactionId()
	{
		return $this->getData(self::LAST_GATEWAY_TRANSACTION_ID);
	}

	public function setLastGatewayTransactionId($id)
	{
		return $this->setData(self::LAST_GATEWAY_TRANSACTION_ID, $id);
	}

	public function getIsActive(): int
	{
		return (int)$this->getData(self::IS_ACTIVE);
	}

	public function setIsActive($isActive)
	{
		return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
	}

	public function getChangePaymentCard(): ?string
	{
		$val = $this->getData(self::CHANGE_PAYMENT_CARD);
		return $val !== null ? (string)$val : null;
	}

	public function setChangePaymentCard(?string $label)
	{
		return $this->setData(self::CHANGE_PAYMENT_CARD, $label);
	}
}
