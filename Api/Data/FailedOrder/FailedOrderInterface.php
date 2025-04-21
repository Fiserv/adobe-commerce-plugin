<?php

namespace Fiserv\Payments\Api\Data\FailedOrder;

interface FailedOrderInterface
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
	 * Get Order state
	 *
	 * @return string|null
	 */
	public function getOrderState();

	/**
	 * Set Order state
	 *
	 * @param string $orderState
	 * @return $this
	 */
	public function setOrderState($orderState);
	
	public function getBillingAddress();
	
	public function setBillingAddress($billingAddress);
	
	public function getShippingAddress();
	
	public function setShippingAddress($shippingAddress);

	public function getCustomerName();

	public function setCustomerName($customerName);

	public function getCustomerEmail();

	public function setCustomerEmail($customerEmail);

	public function getSubtotal();

	public function setSubtotal($subtotal);

	public function getShipping();

	public function setShipping($shipping);

	public function getGrandTotal();

	public function setGrandTotal($grandTotal);

	public function getNotes();

	public function setNotes($notes);

	public function getItems();

	public function setItems($items);

	public function getStoreCredit();

	public function setStoreCredit($storeCredit);
}
