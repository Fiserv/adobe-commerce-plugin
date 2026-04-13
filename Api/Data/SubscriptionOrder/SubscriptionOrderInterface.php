<?php
namespace Fiserv\Payments\Api\Data\SubscriptionOrder;

interface SubscriptionOrderInterface
{
	const ENTITY_ID = 'entity_id';
	const CUSTOMER_NAME = 'customer_name';
	const ORDER_INCREMENT_ID = 'order_increment_id';
	const STATUS = 'status';
	const INTERVAL_VALUE = 'interval_value';
	const INTERVAL_UNIT = 'interval_unit';
	const NEXT_BILLING_DATETIME = 'next_billing_datetime';
	const SCHEME_REFERENCE_TRANSACTION_ID = 'scheme_reference_transaction_id';
	const PAYMENT_TOKEN = 'payment_token';
	const TOKEN_SOURCE = 'token_source';
	const FAILED_ATTEMPTS = 'failed_attempts';
	const CREATED_AT = 'created_at';
	const UPDATED_AT = 'updated_at';
	const SEQUENCE = 'sequence';
	const EXPIRATION_MONTH = 'expiration_month';
	const EXPIRATION_YEAR = 'expiration_year';
	const ORIGINAL_ORDER_INCREMENT = 'original_order_increment';
	const CUSTOMER_ID = 'customer_id';
	const CUSTOMER_EMAIL = 'customer_email';
	const LAST_GATEWAY_TRANSACTION_ID = 'last_gateway_transaction_id';
	const IS_ACTIVE = 'is_active';
	const CHANGE_PAYMENT_CARD = 'change_payment_card';

	/** @return int|null */
	public function getEntityId();

	/** @param int $entityId
	 *  @return $this
	 */
	public function setEntityId($entityId);

	/** @return string|null */
	public function getCustomerName();

	/** @param string $customerName
	 *  @return $this
	 */
	public function setCustomerName($customerName);

	/** @return string */
	public function getOrderIncrementId();

	/** @param string $orderIncrementId
	 *  @return $this
	 */
	public function setOrderIncrementId($orderIncrementId);

	/** @return string */
	public function getStatus();

	/** @param string $status
	 *  @return $this
	 */
	public function setStatus($status);

	/** @return int */
	public function getIntervalValue();

	/** @param int $intervalValue
	 *  @return $this
	 */
	public function setIntervalValue($intervalValue);

	/** @return string */
	public function getIntervalUnit();

	/** @param string $intervalUnit
	 *  @return $this
	 */
	public function setIntervalUnit($intervalUnit);

	/** @return string|null ISO 8601 / MySQL datetime */
	public function getNextBillingDatetime();

	/** @param string $dateTime
	 *  @return $this
	 */
	public function setNextBillingDatetime($dateTime);

	/** @return string|null */
	public function getSchemeReferenceTransactionId();

	/** @param string $id
	 *  @return $this
	 */
	public function setSchemeReferenceTransactionId($id);

	/** @return string|null */
	public function getPaymentToken();

	/** @param string $token
	 *  @return $this
	 */
	public function setPaymentToken($token);

	/** @return string|null */
	public function getTokenSource();

	/** @param string $source
	 *  @return $this
	 */
	public function setTokenSource($source);

	/** @return int */
	public function getFailedAttempts();

	/** @param int $attempts
	 *  @return $this
	 */
	public function setFailedAttempts($attempts);

	/** @return string|null */
	public function getCreatedAt();

	/** @param string $createdAt
	 *  @return $this
	 */
	public function setCreatedAt($createdAt);

	/** @return string|null */
	public function getUpdatedAt();

	/** @param string $updatedAt
	 *  @return $this
	 */
	public function setUpdatedAt($updatedAt);

	/** @return string|null */
	public function getSequence();

	/** @param string $sequence
	 *  @return $this
	 */
	public function setSequence($sequence);

	/* --- expiry accessors --- */

	/** @return string|null */
	public function getExpirationMonth();

	/** @param string $month
	 *  @return $this
	 */
	public function setExpirationMonth($month);

	/** @return string|null */
	public function getExpirationYear();

	/** @param string $year
	 *  @return $this
	 */
	public function setExpirationYear($year);

	/** @return string|null */
	public function getOriginalOrderIncrement();

	/** @param string $orderIncrement
	 *  @return $this
	 */
	public function setOriginalOrderIncrement($orderIncrement);

	/** @return int|null */
	public function getCustomerId();

	/** @param int $customerId
	 *  @return $this
	 */
	public function setCustomerId($customerId);

	/** @return string|null */
	public function getCustomerEmail();

	/** @param string $email
	 *  @return $this
	 */
	public function setCustomerEmail($email);

	public function getLastGatewayTransactionId();

	/** @param string $id
	 *  @return $this
	 */
	public function setLastGatewayTransactionId($id);

	/**
	 * @return int 1 = recurring payment still active, 0 = recurring payment ended
	 */
	public function getIsActive(): int;

	/**
	 * @param int|bool $isActive
	 * @return $this
	 */
	public function setIsActive($isActive);

	/**
	 * Masked card label stored when the user updates the payment card (e.g. "************1111").
	 * Cleared automatically after the next successful renewal.
	 * @return string|null
	 */
	public function getChangePaymentCard(): ?string;

	/**
	 * @param string|null $label
	 * @return $this
	 */
	public function setChangePaymentCard(?string $label);
}