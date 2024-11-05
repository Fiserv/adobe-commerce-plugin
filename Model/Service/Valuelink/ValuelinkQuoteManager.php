<?php

namespace Fiserv\Payments\Model\Service\Valuelink;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Fiserv\Payments\Model\Valuelink\ValuelinkQuoteRecord;
use Fiserv\Payments\Helper\Valuelink\DataHelper;
use Fiserv\Payments\Gateway\Config\Valuelink\Config as ValuelinkConfig;

class ValuelinkQuoteManager
{
	/**
	 *
	 * @var Json
	 */
	private $serializer;

	/**
	 * @var \Magento\Quote\Api\CartRepositoryInterface
	 */
	protected $quoteRepository;

	/**
	 * @var ValuelinkConfig
	 */
	private $valuelinkConfig;

	private $valuelinkDataHelper;

	public function __construct(
		Json $serializer,
		\Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
		DataHelper $valuelinkDataHelper,
		ValuelinkConfig $valuelinkConfig
	) {
		$this->serializer = $serializer;
		$this->quoteRepository = $quoteRepository;
		$this->valuelinkConfig = $valuelinkConfig;
		$this->valuelinkDataHelper = $valuelinkDataHelper;
	}

	public function AddValuelinkCardToQuote(ValuelinkQuoteRecord $valuelinkQuoteRecord, $quote)
	{
		/** @var  \Magento\Quote\Model\Quote $quote */
		if (!$quote->getItemsCount()) {
			throw new CouldNotSaveException(__('The "%1" Cart doesn\'t contain products.', $quote->getId()));
		}

		// Can't add Valuelink cards to Quotes with 0 dollar total
		$grandTotal = $quote->getGrandTotal();
		if ($grandTotal < 0.01)
		{
			throw new LocalizedException(
				__('Quote is already satisfied by existing valuelink cards and/or discounts.')
			);
		}

		$valuelinkRecords = $this->valuelinkDataHelper->getValuelinkRecordsFromQuote($quote);

		$cardAmountLimit = $this->valuelinkConfig->getCardAmountLimit();
		if(sizeof($valuelinkRecords) >= $cardAmountLimit)
		{
			throw new LocalizedException(
				__('Only up to ' . $cardAmountLimit . ' gift card' . ($cardAmountLimit === 1 ? '' : 's') .' can be applied to a single purchase.')
			);
		}

		foreach ($valuelinkRecords as $record)
		{
			if ($record[ValuelinkQuoteRecord::SESSION_ID_KEY] == $valuelinkQuoteRecord->getSessionId())
			{
				throw new LocalizedException(
					__('This Valuelink card account is already in the quote.')
				);
			}
		}

		// Set amount to charge equal to remaining balance on quote
		// if it is less than the balance on the Valuelink card
		$balance = $valuelinkQuoteRecord->getBalance();
		$amountToCharge = $balance > $grandTotal ? $grandTotal : $balance;
		$valuelinkQuoteRecord->setAmountToCharge($amountToCharge);

		array_push($valuelinkRecords, $valuelinkQuoteRecord->getAsArray());
		$this->valuelinkDataHelper->addValuelinkRecordsToQuote($quote, $valuelinkRecords);

		$quote->collectTotals();
		$this->quoteRepository->save($quote);

	}

	public function RemoveAllValuelinkCardsFromQuote($cartId)
	{
		$quote = $this->getActiveOrNonActiveQuote($cartId);
		$this->valuelinkDataHelper->addValuelinkRecordsToQuote($quote, []);
		
		$quote->collectTotals();
		$this->quoteRepository->save($quote);
	}

	private function getActiveOrNonActiveQuote($cartId)
	{
		try {
			return $this->quoteRepository->getActive($cartId);
		} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
			// Active quote not found, try to get the non-active quote
			return $this->quoteRepository->get($cartId);
		}
	}

	public function RemoveValuelinkCardsFromQuote(array $valuelinkQuoteRecords, $quote)
	{
		$valuelinkRecords = $this->valuelinkDataHelper->getValuelinkRecordsFromQuote($quote);

		$validCards = array();

		for ($i = 0; $i < count($valuelinkRecords); $i++)
		{
			$valid = true;
			foreach($valuelinkQuoteRecords as $record)
			{
				if ($valuelinkRecords[$i][ValuelinkQuoteRecord::SESSION_ID_KEY] == $record->getSessionId())
				{
					$valid = false;
				}
			}

			if ($valid)
			{
				array_push($validCards, $valuelinkRecords[$i]);
			}
		}

		// Recalculate amount to charge for remaining cards
		// in case removing one card has freed up balance for
		// another card to handle.
		$grandTotal = $quote->getGrandTotal();
		$valuelinkTotal = 0;
		foreach ($validCards as &$validCard)
		{
			$_tempTotal =+ $valuelinkTotal + $validCard[ValuelinkQuoteRecord::BALANCE_KEY];
			$validCard[ValuelinkQuoteRecord::CHARGE_AMOUNT_KEY] = $_tempTotal > $grandTotal ? $_tempTotal - $grandTotal : $validCard[ValuelinkQuoteRecord::BALANCE_KEY];
			$valuelinkTotal += $validCard[ValuelinkQuoteRecord::CHARGE_AMOUNT_KEY];
		}

		$this->valuelinkDataHelper->addValuelinkRecordsToQuote($quote, $validCards);

		$quote->collectTotals();
		$this->quoteRepository->save($quote);
	}
}
