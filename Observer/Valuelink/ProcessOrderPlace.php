<?php

namespace Fiserv\Payments\Observer\Valuelink;

use Magento\Framework\Event\ObserverInterface;
use Fiserv\Payments\Model\Valuelink\ValuelinkQuoteRecord;
use Fiserv\Payments\Model\Service\Valuelink\ValuelinkQuoteManager;
use Fiserv\Payments\Model\Service\Valuelink\ValuelinkTransactionManager;
use Fiserv\Payments\Model\Service\CommerceHub\FailedOrderManager;
use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Fiserv\Payments\Logger\MultiLevelLogger;

class ProcessOrderPlace implements ObserverInterface
{
    /**
     * Valuelink account data
     *
     * @var \Fiserv\Payments\Helper\Valuelink\DataHelper
     */
    protected $valuelinkHelper;

    /**
     * Valuelink Transaction Factory 
     *
     * @var \Fiserv\Payments\Model\Valuelink\ValuelinkTransactionFactory 
     */
    //protected $valuelinkTxnFactory;

	private $logger;

	private $quoteManager;

	private $valuelinkTxnManager;

	private $serializer;

	private $orderRepository;
	
	private $orderFactory;
	
	private $failedOrderManager;

	private $valuelinkResource;

    /**
     * @param \Magento\GiftCardAccount\Helper\Data $giftCAHelper
     * @param \Fiserv\Payments\Model\Valuelink\ValuelinkTransactionFactory $valuelinkTxnFactory
     */
    public function __construct(
        \Fiserv\Payments\Helper\Valuelink\DataHelper $valuelinkHelper,
		ValuelinkQuoteManager $quoteManager,
		ValuelinkTransactionManager $valuelinkTxnManager,
		Json $serializer,
		OrderRepositoryInterface $orderRepository,
		OrderFactory $orderFactory,
		MultiLevelLogger $logger,
		FailedOrderManager $failedOrderManager,
		ValuelinkResource $valuelinkResource
	) {
        $this->valuelinkHelper = $valuelinkHelper;
		$this->quoteManager = $quoteManager;
		$this->valuelinkTxnManager = $valuelinkTxnManager;
		$this->serializer = $serializer;
		$this->orderRepository = $orderRepository;
		$this->orderFactory = $orderFactory;
		$this->logger = $logger;
		$this->failedOrderManager = $failedOrderManager;
		$this->valuelinkResource = $valuelinkResource;
	}

    /**
     * Charge all gift cards applied to the order
     * used for event: sales_order_place_after
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();
		$order = $observer->getEvent()->getOrder();

		/** @var \Magento\Quote\Model\Quote\Address $address */
        $address = $observer->getEvent()->getAddress();
        if (!$address) {
            // Single address checkout.
            /** @var \Magento\Quote\Model\Quote $quote */
            $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        }

		$valuelinkCards = $this->serializer->unserialize($address->getValuelinkCards());
		if (is_array($valuelinkCards) && count($valuelinkCards) > 0)
		{
			// Session IDs produced through Secure Card Capture
			// are only valid for 30 minutes. If any stale cards
			// exist, remove them and fail the order.
			$expiredCards = array();
			foreach($valuelinkCards as $card)
			{
				if (!$this->valuelinkHelper->isValuelinkRecordValid($card[ValuelinkQuoteRecord::TIMESTAMP_KEY]))
				{
					array_push($expiredCards, ValuelinkQuoteRecord::createFromArray($card));
				}
			}

			if (count($expiredCards) > 0 )
			{
				$order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
				$this->quoteManager->RemoveValuelinkCardsFromQuote($expiredCards, $observer->getEvent()->getQuote());	
				throw new LocalizedException(
					__("Quote contained stale gift cards which had to be removed. Recapture stale gift cards and try again.")
				);
			}

			// Should only have valid card captures at this point
			$chargedTxns = array();
			try
			{
				foreach($valuelinkCards as $card)
				{
					$this->valuelinkTxnManager->chargeValuelinkCard($order, ValuelinkQuoteRecord::createFromArray($card));
					$txns = $this->valuelinkResource->getByChRequestLike($card['sessionId']);
					array_push($chargedTxns, $txns);
				}
			}
			catch(\Exception $e)
			{
				$this->logger->logError(1, "Gift Card charge failed. Reverting...");

				foreach ($chargedTxns as $txn)
				{
					try {
						$this->valuelinkTxnManager->cancelValuelinkTransaction($order, $txn[0]);
					} catch (\Exception $cancelEx) {
						$this->logger->logError(2, "Failed to cancel gift card transaction: " . $cancelEx->getMessage());
					}
				}

				// Valuelink Cards should all be invalidated.
				// We do not know which cards were processed and canceled,
				// which cards weren't run, and which cards errored out
				$cardsToRemove = array();
				foreach ($valuelinkCards as $card)
				{
					array_push($cardsToRemove, ValuelinkQuoteRecord::createFromArray($card));
				}
				$this->quoteManager->RemoveValuelinkCardsFromQuote($cardsToRemove, $observer->getEvent()->getQuote());

				$failedOrder = $this->failedOrderManager->createFailedOrder($order);
				$this->failedOrderManager->saveFailedOrder($failedOrder, $order->getQuoteId(), $order->getStoreId());

				throw new LocalizedException(__("Gift card(s) applied to this order could not be redeemed and were removed."));	
			}
		}

        return $this;
	}
}
