<?php

namespace Fiserv\Payments\Model;

use Fiserv\Payments\Api\Data\FailedOrder\FailedOrderInterface;
use Magento\Framework\Model\AbstractModel;

class FailedOrder extends AbstractModel implements FailedOrderInterface
{
	public const KEY_ENTITY_ID = "entity_id";
	public const KEY_ORDER_INCREMENT_ID = "order_increment_id";
	public const KEY_DATE_TIME = "date_time";
	public const KEY_ORDER_STATE = "order_state";
	public const KEY_BILLING_ADDRESS = "billing_address";
	public const KEY_SHIPPING_ADDRESS = "shipping_address";
	public const KEY_CUSTOMER_NAME = "customer_name";
	public const KEY_CUSTOMER_EMAIL = "customer_email";
	public const KEY_SUBTOTAL = "subtotal";
	public const KEY_SHIPPING = "shipping_handling";
	public const KEY_GRAND_TOTAL = "grandTotal";
	public const KEY_NOTES = "notes";
	public const KEY_ITEMS = "items";
	public const KEY_ITEM_NAME = "name";
	public const KEY_ITEM_QTY = "qty";
	public const KEY_ITEM_PRICE = "price";
	public const KEY_ITEM_ID = "product_id";
	public const KEY_STORE_CREDIT = "storeCredit";
	public const KEY_DISCOUNT_CODE = "discountCode";

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

	public function getOrderState()
	{
		return $this->getData(self::KEY_ORDER_STATE);
	}

	public function setOrderState($orderState)
	{
		return $this->setData(self::KEY_ORDER_STATE, $orderState);
	}

	public function getBillingAddress()
	{
		return $this->getData(self::KEY_BILLING_ADDRESS);
	}

	public function setBillingAddress($billingAddress)
	{
		return $this->setData(self::KEY_BILLING_ADDRESS, $billingAddress);
	}

	public function getShippingAddress()
	{
		return $this->getData(self::KEY_SHIPPING_ADDRESS);
	}

	public function setShippingAddress($shippingAddress)
	{
		return $this->setData(self::KEY_SHIPPING_ADDRESS, $shippingAddress);
	}

	public function getCustomerName()
	{
		return $this->getData(self::KEY_CUSTOMER_NAME);
	}

	public function setCustomerName($customerName)
	{
		return $this->setData(self::KEY_CUSTOMER_NAME, $customerName);
	}

	public function getCustomerEmail()
	{
		return $this->getData(self::KEY_CUSTOMER_EMAIL);
	}

	public function setCustomerEmail($customerEmail)
	{
		return $this->setData(self::KEY_CUSTOMER_EMAIL, $customerEmail);
	}

	public function getSubtotal()
	{
		return $this->getData(self::KEY_SUBTOTAL);
	}

	public function setSubtotal($subtotal)
	{
		return $this->setData(self::KEY_SUBTOTAL, $subtotal);
	}

	public function getShipping()
	{
		return $this->getData(self::KEY_SHIPPING);
	}

	public function setShipping($shipping)
	{
		return $this->setData(self::KEY_SHIPPING, $shipping);
	}

	public function getGrandTotal()
	{
		return $this->getData(self::KEY_GRAND_TOTAL);
	}

	public function setGrandTotal($grandTotal)
	{
		return $this->setData(self::KEY_GRAND_TOTAL, $grandTotal);
	}

	public function getNotes()
	{
		return $this->getData(self::KEY_NOTES);
	}

	public function setNotes($notes)
	{
		return $this->setData(self::KEY_NOTES, $notes);
	}

	public function getItems()
	{
		return $this->getData(self::KEY_ITEMS);
	}

	public function setItems($items)
	{
		return $this->setData(self::KEY_ITEMS, $items);
	}

	public function getStoreCredit()
	{
		return $this->getData(self::KEY_STORE_CREDIT);
	}

	public function setStoreCredit($storeCredit)
	{
		return $this->setData(self::KEY_STORE_CREDIT, $storeCredit);
	}

	public function getDiscountCode()
	{
		return $this->getDiscountCode(self::KEY_DISCOUNT_CODE);
	}

	public function setDiscountCode($discountCode)
	{
		return $this->setData(self::KEY_DISCOUNT_CODE, $discountCode);
	}
}
