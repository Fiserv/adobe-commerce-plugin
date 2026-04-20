<?php

namespace Fiserv\Payments\Api\Data\OpenRefund;

interface OpenRefundInterface
{
    const KEY_ENTITY_ID               = 'entity_id';
    const KEY_AMOUNT                  = 'amount';
    const KEY_CURRENCY_CODE           = 'currency_code';
    const KEY_CUSTOMER_ID             = 'customer_id';
    const KEY_CUSTOMER_EMAIL          = 'customer_email';
    const KEY_CUSTOMER_NAME           = 'customer_name';
    const KEY_ADMIN_USER_ID           = 'admin_user_id';
    const KEY_STATUS                  = 'status';
    const KEY_TRANSACTION_ID          = 'transaction_id';
    const KEY_MASKED_CARD             = 'masked_card';
    const KEY_REFERENCE_TRANSACTION_ID = 'reference_transaction_id';
    const KEY_NOTES                   = 'notes';
    const KEY_CREATED_AT              = 'created_at';

    public function getEntityId();
    public function setEntityId($entityId);

    public function getAmount();
    public function setAmount($amount);

    public function getCurrencyCode();
    public function setCurrencyCode($currencyCode);

    public function getCustomerId();
    public function setCustomerId($customerId);

    public function getCustomerEmail();
    public function setCustomerEmail($customerEmail);

    public function getCustomerName();
    public function setCustomerName($customerName);

    public function getAdminUserId();
    public function setAdminUserId($adminUserId);

    public function getStatus();
    public function setStatus($status);

    public function getTransactionId();
    public function setTransactionId($transactionId);

    public function getMaskedCard();
    public function setMaskedCard($maskedCard);

    public function getReferenceTransactionId();
    public function setReferenceTransactionId($referenceTransactionId);

    public function getNotes();
    public function setNotes($notes);

    public function getCreatedAt();
    public function setCreatedAt($createdAt);
}

