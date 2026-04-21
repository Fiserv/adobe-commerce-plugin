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

    public function getEntityId()               { return $this->getData(self::KEY_ENTITY_ID); }
    public function setEntityId($v)             { return $this->setData(self::KEY_ENTITY_ID, $v); }
    public function getAmount()                 { return $this->getData(self::KEY_AMOUNT); }
    public function setAmount($v)               { return $this->setData(self::KEY_AMOUNT, $v); }
    public function getCurrencyCode()           { return $this->getData(self::KEY_CURRENCY_CODE); }
    public function setCurrencyCode($v)         { return $this->setData(self::KEY_CURRENCY_CODE, $v); }
    public function getCustomerId()             { return $this->getData(self::KEY_CUSTOMER_ID); }
    public function setCustomerId($v)           { return $this->setData(self::KEY_CUSTOMER_ID, $v); }
    public function getCustomerEmail()          { return $this->getData(self::KEY_CUSTOMER_EMAIL); }
    public function setCustomerEmail($v)        { return $this->setData(self::KEY_CUSTOMER_EMAIL, $v); }
    public function getCustomerName()           { return $this->getData(self::KEY_CUSTOMER_NAME); }
    public function setCustomerName($v)         { return $this->setData(self::KEY_CUSTOMER_NAME, $v); }
    public function getAdminUserId()            { return $this->getData(self::KEY_ADMIN_USER_ID); }
    public function setAdminUserId($v)          { return $this->setData(self::KEY_ADMIN_USER_ID, $v); }
    public function getStatus()                 { return $this->getData(self::KEY_STATUS); }
    public function setStatus($v)               { return $this->setData(self::KEY_STATUS, $v); }
    public function getTransactionId()          { return $this->getData(self::KEY_TRANSACTION_ID); }
    public function setTransactionId($v)        { return $this->setData(self::KEY_TRANSACTION_ID, $v); }
    public function getMaskedCard()             { return $this->getData(self::KEY_MASKED_CARD); }
    public function setMaskedCard($v)           { return $this->setData(self::KEY_MASKED_CARD, $v); }
    public function getNotes()                  { return $this->getData(self::KEY_NOTES); }
    public function setNotes($v)                { return $this->setData(self::KEY_NOTES, $v); }
    public function getCreatedAt()              { return $this->getData(self::KEY_CREATED_AT); }
    public function setCreatedAt($v)            { return $this->setData(self::KEY_CREATED_AT, $v); }
}
