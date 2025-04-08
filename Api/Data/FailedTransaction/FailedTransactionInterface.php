<?php

namespace Fiserv\Payments\Api\Data\FailedTransaction;

interface FailedTransactionInterface
{
	/**
	 * Get entity ID
	 *
	 * @return int|null
	 */
	public function getEntityId();

	/**
	 * Set entity ID
	 *
	 * @param int $entityId
	 * @return $this
	 */
	public function setEntityId($entityId);

	/**
	 * Get order increment ID
	 *
	 * @return string|null
	 */
	public function getOrderIncrementId();

	/**
	 * Set order increment ID
	 *
	 * @param string $orderIncrementId
	 * @return $this
	 */
	public function setOrderIncrementId($orderIncrementId);

	/**
	 * Get date and time
	 *
	 * @return string|null
	 */
	public function getDateTime();

	/**
	 * Set date and time
	 *
	 * @param string $dateTime
	 * @return $this
	 */
	public function setDateTime($dateTime);

	/**
	 * Get transaction state
	 *
	 * @return string|null
	 */
	public function getTransactionState();

	/**
	 * Set transaction state
	 *
	 * @param string $transactionState
	 * @return $this
	 */
	public function setTransactionState($transactionState);

	/**
	 * Get approval status
	 *
	 * @return string|null
	 */
	public function getApprovalStatus();

	/**
	 * Set approval status
	 *
	 * @param string $approvalStatus
	 * @return $this
	 */
	public function setApprovalStatus($approvalStatus);

	/**
	 * Get total amount
	 *
	 * @return float|null
	 */
	public function getTotalAmount();

	/**
	 * Set total amount
	 *
	 * @param float $totalAmount
	 * @return $this
	 */
	public function setTotalAmount($totalAmount);

	/**
	 * Get remote IP
	 *
	 * @return string|null
	 */
	public function getRemoteIp();

	/**
	 * Set remote IP
	 *
	 * @param string $remoteIp
	 * @return $this
	 */
	public function setRemoteIp($remoteIp);

	/**
	 * Get transaction ID
	 *
	 * @return string|null
	 */
	public function getTransactionId();

	/**
	 * Set transaction ID
	 *
	 * @param string $transactionId
	 * @return $this
	 */
	public function setTransactionId($transactionId);

	/**
	 * Get currency
	 *
	 * @return string|null
	 */
	public function getCurrency();

	/**
	 * Set currency
	 *
	 * @param string $currency
	 * @return $this
	 */
	public function setCurrency($currency);

	/**
	 * Get bank association details
	 *
	 * @return string|null
	 */
	public function getBankAssociationDetails();

	/**
	 * Set bank association details
	 *
	 * @param string $bankAssociationDetails
	 * @return $this
	 */
	public function setBankAssociationDetails($bankAssociationDetails);

	/**
	 * Get country
	 *
	 * @return string|null
	 */
	public function getCountry();

	/**
	 * Set country
	 *
	 * @param string $country
	 * @return $this
	 */
	public function setCountry($country);

	/**
	 * Get apiTraceId
	 *
	 * @return string|null
	 */
	public function getApiTraceId();

	/**
	 * Set apiTraceId
	 *
	 * @param string $apiTraceId
	 * @return $this
	 */
	public function setApiTraceId($apiTraceId);

	public function getHostResponseMessage();

	public function setHostResponseMessage($hostResponseMessage);
}
