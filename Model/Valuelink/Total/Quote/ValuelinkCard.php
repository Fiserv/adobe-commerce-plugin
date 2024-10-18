<?php

namespace Fiserv\Payments\Model\Valuelink\Total\Quote;

use Fiserv\Payments\Helper\Valuelink\DataHelper;
use Fiserv\Payments\Model\Valuelink\ValuelinkQuoteRecord;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

class ValuelinkCard extends AbstractTotal
{
	public const TOTAL_SEGMENT = "fiserv_valuelink";

	private $valuelinkDataHelper;
	private $priceCurrency;

	public function __construct(
		DataHelper $valuelinkDataHelper,
		PriceCurrencyInterface $priceCurrency
	){
		$this->valuelinkDataHelper = $valuelinkDataHelper;
		$this->priceCurrency = $priceCurrency;
		$this->setCode(self::TOTAL_SEGMENT);
	}

	/*
	 * @inheritDoc
	 */
	public function _resetState(): void
	{
		parent::_resetState();
		$this->setCode(self::TOTAL_SEGMENT);
	}

	public function collect(
		Quote $quote,
		ShippingAssignmentInterface $shippingAssignment,
		Total $total) 
	{
		$baseAmount = $quote->getBaseValuelinkCardsAmount();
		$giftAmount = $quote->getValuelinkCardsAmount();
		
		if ($baseAmount >= $total->getBaseGrandTotal()) {
			$baseAdjustedGiftAmount = $total->getBaseGrandTotal();
			$adjustedGiftAmount = $total->getGrandTotal();

			$total->setBaseGrandTotal(0);
			$total->setGrandTotal(0);
		} else {
			$baseAdjustedGiftAmount = $baseAmount;
			$adjustedGiftAmount = $giftAmount;

			$total->setBaseGrandTotal($total->getBaseGrandTotal() - $baseAmount);
			$total->setGrandTotal($total->getGrandTotal() - $giftAmount);
		}

		$addressCards = [];

		$quoteCards = $this->valuelinkDataHelper->getValuelinkRecordsFromQuote($quote);
		if ($baseAdjustedGiftAmount) {
			$baseAdjustedUsedGiftAmountLeft = $baseAdjustedGiftAmount;
			$adjustedUsedGiftAmountLeft = $adjustedGiftAmount;
			foreach ($quoteCards as &$quoteCard) {
				$card = $quoteCard;
				if ($baseAdjustedUsedGiftAmountLeft > 0) {
					$thisCardUsedAmount = min(
						$quoteCard[ValuelinkQuoteRecord::BALANCE_KEY],
						$baseAdjustedUsedGiftAmountLeft
					);
					$baseAdjustedUsedGiftAmountLeft -= $thisCardUsedAmount;
				} else {
					$thisCardUsedAmount = 0;
				}

				$card[ValuelinkQuoteRecord::CHARGE_AMOUNT_KEY] = round($thisCardUsedAmount, 4);
				if ($card[ValuelinkQuoteRecord::CHARGE_AMOUNT_KEY] >= 0.01)
				{
					$addressCards[] = $card;
				}
			}
		}

		$this->valuelinkDataHelper->addValuelinkRecordsToTotal($total, $addressCards);

		$total->setBaseValuelinkCardsAmount($baseAdjustedGiftAmount);
		$total->setValuelinkCardsAmount($adjustedGiftAmount);

		return $this;
	}


	public function fetch(Quote $quote, Total $total)
	{
		$valuelinkCards = $this->valuelinkDataHelper->getValuelinkRecordsFromTotal($total);
		if (!empty($valuelinkCards)) {
			return [
				'code' => $this->getCode(),
				'title' => __('Valuelink Gift Cards'),
				'value' => -$total->getValuelinkCardsAmount(),
				'valuelink_cards' => $valuelinkCards
			];
		}

		return null;
	}
}
