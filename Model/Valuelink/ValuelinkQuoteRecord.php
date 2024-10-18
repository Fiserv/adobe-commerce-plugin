<?php

namespace Fiserv\Payments\Model\Valuelink;

use Fiserv\Payments\Api\Data\ValuelinkQuoteRecordInterface;
use Magento\Framework\Exception\LocalizedException;

class ValuelinkQuoteRecord implements ValuelinkQuoteRecordInterface
{
	public const SESSION_ID_KEY = "sessionId";
	public const BALANCE_KEY = "balance";
	public const CHARGE_AMOUNT_KEY = "amountToCharge";
	public const TIMESTAMP_KEY = "capturedTimestamp";

	public static function createFromArray(array $valuelinkArray)
	{
		if(!isset($valuelinkArray[self::SESSION_ID_KEY]))
		{
			throw new LocalizedException(
				__('Required sessionId parameter not found.')
			);
		}		

		if(!isset($valuelinkArray[self::BALANCE_KEY]))
		{
			throw new LocalizedException(
				__('Required balance parameter not found.')
			);
		}	
		
		if(!isset($valuelinkArray[self::TIMESTAMP_KEY]))
		{
			throw new LocalizedException(
				__('Required captured timestamp parameter not found.')
			);
		}	

		$record = new ValuelinkQuoteRecord();
		$record->setSessionId($valuelinkArray[self::SESSION_ID_KEY]);
		$record->setBalance($valuelinkArray[self::BALANCE_KEY]);
		$amountToCharge = $valuelinkArray[self::CHARGE_AMOUNT_KEY] ?? $valuelinkArray[self::BALANCE_KEY];
		$record->setAmountToCharge($amountToCharge);
		$record->setCapturedTimestamp($valuelinkArray[self::TIMESTAMP_KEY]);

		return $record;
	}

	/**
     * @var string
     */
    private $sessionId;

    /**
     * @var float
     */
    private $balance;

    /**
     * @var float
     */
    private $amountToCharge;

	/**
	 * @var integer
	 */
	private $capturedTimestamp;

    /**
     * @inheritdoc
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * @inheritdoc
     */
    public function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getBalance()
    {
        return $this->balance;
    }

    /**
     * @inheritdoc
     */
    public function setBalance($balance)
    {
        $this->balance = $balance;
        return $this;
	}

	/**
     * @inheritdoc
     */
    public function getAmountToCharge()
    {
        return $this->amountToCharge;
    }

    /**
     * @inheritdoc
     */
    public function setAmountToCharge($amountToCharge)
    {
        $this->amountToCharge = $amountToCharge;
        return $this;
    }

	    /**
     * @inheritdoc
     */
    public function getCapturedTimestamp()
    {
        return $this->capturedTimestamp;
    }

    /**
     * @inheritdoc
     */
    public function setCapturedTimestamp($timestamp)
    {
        $this->capturedTimestamp = $timestamp;
        return $this;
	}

	public function getAsArray()
	{
		return array(
			self::SESSION_ID_KEY => $this->getSessionId(),
			self::BALANCE_KEY => $this->getBalance(),
			self::CHARGE_AMOUNT_KEY => $this->getAmountToCharge() ?? $this->getBalance(),
			self::TIMESTAMP_KEY => $this->getCapturedTimestamp()
		);
	}
}
