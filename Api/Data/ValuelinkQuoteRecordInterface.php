<?php

namespace Fiserv\Payments\Api\Data;

/**
 * Valuelink Quote Record data
 *
 * @codeCoverageIgnore
 * @api
 * @since 1.0.0
 */
interface ValuelinkQuoteRecordInterface
{
    /**
     * Get Session Id
     *
     * @return string
     * @since 1.0.0
     */
    public function getSessionId();

    /**
     * Set Session Id
     *
     * @param string $transactionId
     * @return $this
     * @since 1.0.0
     */
    public function setSessionId($sessionId);

    /**
     * Get Transaction Type
     *
     * @return string
     * @since 1.0.0
     */
	public function getBalance();

	/**
	 * Set Balance
	 *
	 * @param decimal $balance
	 * @return $this
	 * @since 1.0.0
	 */
	public function setBalance($balance);
}	
