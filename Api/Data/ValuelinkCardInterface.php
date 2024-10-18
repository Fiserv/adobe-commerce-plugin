<?php

namespace Fiserv\Payments\Api\Data;

use Fiserv\Payments\Api\Data\ValuelinkTransactionInterface;

/**
 * Valuelink Card data
 *
 * @codeCoverageIgnore
 * @api
 * @since 1.0.0
 */
interface ValuelinkCardInterface
{
    /**
     * Get Id
     *
     * @return int
     * @since 1.0.0
     */
    public function getId();

    /**
     * Set Id
     *
     * @param int $id
     * @return $this
     * @since 1.0.0
     */
    public function setId($id);

    /**
     * Get SessionId
     *
     * @return string
     * @since 1.0.0
     */
    public function getSessionId();

    /**
     * Set SessionId
     *
     * @param string $sessionId
     * @return $this
     * @since 1.0.0
     */
    public function setSessionId($sessionId);

    /**
     * Get Amount
     *
     * @return float
     * @since 1.0.0
     */
    public function getAmount();

    /**
     * Set Amount
     *
     * @param float $amount
     * @return $this
     * @since 1.0.0
     */
    public function setAmount($amount);

    /**
     * Get Base Amount
     *
     * @return float
     * @since 1.0.0
     */
    public function getBaseAmount();

    /**
     * Set Base Amount
     *
     * @param float $baseAmount
     * @return $this
     * @since 1.0.0
     */
	public function setBaseAmount($baseAmount);    
	
	/**
	 * * Get Transactions
	 * *
	 * * Returns a collection of Valuelink transactions associated with this Valuelink gift card.
	 * *
	 * * @return ValuelinkTransactionInterface[]|null
	 * */
	public function getGiftCards();

	/**
	 * * Set Gift cards codes to use this object as a composite entity.
	 * *
	 * * @param string[] $cards
	 * * @return \Magento\GiftCardAccount\Api\Data\GiftCardAccountInterface
	 * */
	public function setGiftCards(array $cards);
}
