<?php

namespace Fiserv\Payments\Model;

use Fiserv\Payments\Api\Data\OpenRefund\OpenRefundInterface;
use Magento\Framework\Model\AbstractModel;

class OpenRefund extends AbstractModel implements OpenRefundInterface
{
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED  = 'failed';

    protected function _construct()
    {
        $this->_init(\Fiserv\Payments\Model\ResourceModel\OpenRefund::class);
    }

    public function getEntityId()
    {
        return $this->getData(self::KEY_ENTITY_ID);
    }

    public function setEntityId($entityId)
    {
        return $this->setData(self::KEY_ENTITY_ID, $entityId);
    }

    public function getAmount()
    {
        return $this->getData(self::KEY_AMOUNT);
    }

    public function setAmount($amount)
    {
        return $this->setData(self::KEY_AMOUNT, $amount);
    }

    public function getCurrencyCode()
    {
        return $this->getData(self::KEY_CURRENCY_CODE);
    }

    public function setCurrencyCode($currencyCode)
    {
        return $this->setData(self::KEY_CURRENCY_CODE, $currencyCode);
    }

    public function getCustomerId()
    {
        return $this->getData(self::KEY_CUSTOMER_ID);
    }

    public function setCustomerId($customerId)
    {
        return $this->setData(self::KEY_CUSTOMER_ID, $customerId);
    }

    public function getCustomerEmail()
    {
        return $this->getData(self::KEY_CUSTOMER_EMAIL);
    }

    public function setCustomerEmail($customerEmail)
    {
        return $this->setData(self::KEY_CUSTOMER_EMAIL, $customerEmail);
    }

    public function getCustomerName()
    {
        return $this->getData(self::KEY_CUSTOMER_NAME);
    }

    public function setCustomerName($customerName)
    {
        return $this->setData(self::KEY_CUSTOMER_NAME, $customerName);
    }

    public function getAdminUserId()
    {
        return $this->getData(self::KEY_ADMIN_USER_ID);
    }

    public function setAdminUserId($adminUserId)
    {
        return $this->setData(self::KEY_ADMIN_USER_ID, $adminUserId);
    }

    public function getStatus()
    {
        return $this->getData(self::KEY_STATUS);
    }

    public function setStatus($status)
    {
        return $this->setData(self::KEY_STATUS, $status);
    }

    public function getTransactionId()
    {
        return $this->getData(self::KEY_TRANSACTION_ID);
    }

    public function setTransactionId($transactionId)
    {
        return $this->setData(self::KEY_TRANSACTION_ID, $transactionId);
    }

    public function getMaskedCard()
    {
        return $this->getData(self::KEY_MASKED_CARD);
    }

    public function setMaskedCard($maskedCard)
    {
        return $this->setData(self::KEY_MASKED_CARD, $maskedCard);
    }

    public function getReferenceTransactionId()
    {
        return $this->getData(self::KEY_REFERENCE_TRANSACTION_ID);
    }

    public function setReferenceTransactionId($referenceTransactionId)
    {
        return $this->setData(self::KEY_REFERENCE_TRANSACTION_ID, $referenceTransactionId);
    }

    public function getOrderIncrementId()
    {
        return $this->getData(self::KEY_ORDER_INCREMENT_ID);
    }

    public function setOrderIncrementId($orderIncrementId)
    {
        return $this->setData(self::KEY_ORDER_INCREMENT_ID, $orderIncrementId);
    }

    public function getNotes()
    {
        return $this->getData(self::KEY_NOTES);
    }

    public function setNotes($notes)
    {
        return $this->setData(self::KEY_NOTES, $notes);
    }

    public function getCreatedAt()
    {
        return $this->getData(self::KEY_CREATED_AT);
    }

    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::KEY_CREATED_AT, $createdAt);
    }
}

