<?php

namespace Fiserv\Payments\Model\Plugin\Valuelink;

use Fiserv\Payments\Helper\Valuelink\DataHelper;
use Fiserv\Payments\Model\Valuelink\ValuelinkQuoteRecord;
use Magento\Quote\Model\Quote;
use Magento\Framework\Pricing\PriceCurrencyInterface;


/**
 * Plugin to include Valuelink gift cards in totals collection
 */
class TotalsCollector
{
    /**
     * @var DataHelper
     */
    protected $valuelinkDataHelper;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @param Data $giftCardAccountData
     * @param GiftcardaccountFactory $giftCAFactory
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        DataHelper $valuelinkDataHelper,
		PriceCurrencyInterface $priceCurrency
    ) {
        $this->valuelinkDataHelper = $valuelinkDataHelper;
		$this->priceCurrency = $priceCurrency;
    }

    /**
     * Apply before collect
     *
     * @param Quote\TotalsCollector $subject
     * @param Quote $quote
     *
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeCollect(
        Quote\TotalsCollector $subject,
        Quote                 $quote
    ) {
		$this->resetGiftCardAmount($quote);
    }

    /**
     * Apply before collectQuoteTotals
     *
     * @param Quote\TotalsCollector $subject
     * @param Quote $quote
     *
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeCollectQuoteTotals(
        Quote\TotalsCollector $subject,
        Quote                 $quote
    ) {
        $this->resetGiftCardAmount($quote);
    }

    /**
     * Reset quote Valuelink gift cards amount
     *
     * @param Quote $quote
     * @return void
     */
    private function resetGiftCardAmount(Quote $quote) : void
    {
        $quote->setValuelinkCardsAmount(0);
        $quote->setBaseValuelinkCardsAmount(0);

        $baseAmount = 0;
        $amount = 0;
        $cards = $this->valuelinkDataHelper->getValuelinkRecordsFromQuote($quote);

		foreach ($cards as $card) {
            if ($card[ValuelinkQuoteRecord::BALANCE_KEY] >= 0.01) {
				$baseAmount += $card[ValuelinkQuoteRecord::BALANCE_KEY];
				$amount += $this->priceCurrency->round(
                    $this->priceCurrency->convert(
						$card[ValuelinkQuoteRecord::BALANCE_KEY],
						$quote->getStore()
					 )
				);
			} 
		}

        $quote->setValuelinkCardsAmount($baseAmount);
        $quote->setBaseValuelinkCardsAmount($amount);
    }
}
