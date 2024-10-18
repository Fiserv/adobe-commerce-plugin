<?php

namespace Fiserv\Payments\Api\Data\Valuelink;

/**
 * Valuelink Transaction data
 *
 * @codeCoverageIgnore
 * @api
 * @since 1.0.0
 */
interface ValuelinkTransactionInterface
{
    /**
     * Get Transaction Id
     *
     * @return string
     * @since 1.0.0
     */
    public function getTransactionId();

    /**
     * Set Transaction Id
     *
     * @param string $transactionId
     * @return $this
     * @since 1.0.0
     */
    public function setTransactionId($transactionId);

    /**
     * Get Transaction Type
     *
     * @return string
     * @since 1.0.0
     */
    public function getTransactionType();

    /**
     * Set Transaction Type
     *
     * @param string $transactionType
     * @return $this
     * @since 1.0.0
     */
    public function setTransactionType($transactionType);

    /**
     * Get Transaction State
     *
     * @return string
     * @since 1.0.0
     */
    public function getTransactionState();

    /**
     * Set Transaction State
     *
     * @param string $transactionState
     * @return $this
     * @since 1.0.0
     */
    public function setTransactionState($transactionState);

    /**
     * Get CommerceHub Request
     *
     * @return string
     * @since 1.0.0
     */
    public function getChRequest();

    /**
     * Set CommerceHub Request
     *
     * @param string $chRequest
     * @return $this
     * @since 1.0.0
     */
	public function setChRequest($chRequest);

	/**
     * Get CommerceHub Response
     *
     * @return string
     * @since 1.0.0
     */
    public function getChResponse();

    /**
     * Set CommerceHub Response
     *
     * @param string $chResponse
     * @return $this
     * @since 1.0.0
     */
	public function setChResponse($chResponse); 

	public function getAmount();

	public function setAmount($amount);

	public function getCurrency();

	public function setCurrency($currency);

	public function getDateCreated();

	public function setDateCreated($date);

	public function getParentTransactionId();

	public function setParentTransactionId($parentTransactionId);

	public function getOrderId();

	public function setOrderId($orderId);

	public function getOrderIncrementId();

	public function setOrderIncrementId($orderId);

	public function getInvoiceId();

	public function setInvoiceId($invoiceId);

	public function getCreditMemoId();

	public function setCreditMemoId($creditMemoId);
}	
