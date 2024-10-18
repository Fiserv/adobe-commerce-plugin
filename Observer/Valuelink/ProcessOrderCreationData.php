<?php

namespace Fiserv\Payments\Observer\Valuelink;

use Fiserv\Payments\Model\Valuelink\ValuelinkQuoteRecord;
use Fiserv\Payments\Model\Service\Valuelink\ValuelinkQuoteManager;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\Event\ObserverInterface;



class ProcessOrderCreationData implements ObserverInterface
{
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

	private $quoteManager;

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

    /**
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
        \Magento\Framework\Message\ManagerInterface $messageManager,
		ValuelinkQuoteManager $quoteManager,
		MultiLevelLogger $logger
	) {
		$this->messageManager = $messageManager;
		$this->quoteManager = $quoteManager;
		$this->logger = $logger;
    }

    /**
     * Process post data and set usage of Valuelink gift card order creation model
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $model = $observer->getEvent()->getOrderCreateModel();
        $request = $observer->getEvent()->getRequest();
        $quote = $model->getQuote();
        if (isset($request['valuelink_add'])) {
			try {
				$this->logger->logInfo(1, "Attempting to apply gift card to cart...");
				
         		$data = array();
				$data[ValuelinkQuoteRecord::SESSION_ID_KEY] = $request['valuelink_add'];
				$data[ValuelinkQuoteRecord::BALANCE_KEY] = $request['valuelink_balance'];
				$data[ValuelinkQuoteRecord::TIMESTAMP_KEY] = time();

				$quoteRecord = ValuelinkQuoteRecord::createFromArray($data);
				$this->quoteManager->AddValuelinkCardToQuote($quoteRecord, $quote);

				$this->logger->logInfo(1, "Gift card has been applied to cart");
				
			} catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\Exception $e) {
				$this->logger->logError(1, "Gift card failed to be applied");
				$this->logger->logCritical(2, $e);
                $this->messageManager->addException($e, __('Unable to apply gift card.'));
            }
        }

        if (isset($request['valuelink_remove'])) {
            try {
				$this->logger->logInfo(1, "Attempting to remove gift card from cart...");

				$data = array();
				$data[ValuelinkQuoteRecord::SESSION_ID_KEY] = $request['valuelink_remove'];
				$data[ValuelinkQuoteRecord::BALANCE_KEY] = 0;
				$data[ValuelinkQuoteRecord::TIMESTAMP_KEY] = time();
			
				$recordsToRemove = array();
				array_push($recordsToRemove, ValuelinkQuoteRecord::createFromArray($data));

				$this->quoteManager->RemoveValuelinkCardsFromQuote($recordsToRemove, $quote);

				$this->logger->logInfo(1, "Gift card has been removed from cart");
				
			} catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\Exception $e) {
				$this->logger->logError(1, "Error occurred while removing a ValueLink card");
				$this->logger->logCritical(2, $e);
                $this->messageManager->addException($e, __('Unable to remove gift card.'));
            }
        }
        return $this;
    }
}
