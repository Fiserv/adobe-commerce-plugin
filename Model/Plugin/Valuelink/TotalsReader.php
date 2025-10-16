<?php
namespace Fiserv\Payments\Model\Plugin\Valuelink;

use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\ReaderInterface;
use Fiserv\Payments\Model\Service\Valuelink\ValuelinkQuoteManager;
use Fiserv\Payments\Model\Valuelink\ValuelinkQuoteRecord;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider;
 use Fiserv\Payments\Model\Config\ConfigProvider as FiservConfigProvider;
use Fiserv\Payments\Logger\MultiLevelLogger;

class TotalsReader
{
	private $logger;

	protected $totalFactory;

	protected $collectorList;

	private $quoteManager;

	private $configProvider;

	private $fiservConfigProvider;

	public function __construct(
		\Magento\Quote\Model\Quote\Address\TotalFactory $totalFactory,
		\Magento\Quote\Model\Quote\TotalsCollectorList $collectorList,
		ValuelinkQuoteManager $quoteManager,
		ConfigProvider $configProvider,
		FiservConfigProvider $fiservConfigProvider,
		MultiLevelLogger $logger
	) {
		$this->totalFactory = $totalFactory;
		$this->collectorList = $collectorList;
		$this->quoteManager = $quoteManager;
		$this->configProvider = $configProvider;
 		$this->fiservConfigProvider = $fiservConfigProvider;
		$this->logger = $logger;
	}

	public function beforeFetch(
		\Magento\Quote\Model\Quote\TotalsReader $subject,
		\Magento\Quote\Model\Quote $quote,
		array $shippingAssignment
	) {

		$chConfig = $this->configProvider->getConfig()["payment"][ConfigProvider::CODE];
		$fconfig = $this->fiservConfigProvider->getConfig()["payment"][FiservConfigProvider::CODE];

		if ( !$chConfig[ConfigProvider::IS_ACTIVE_KEY] ||  !$fconfig[FiservConfigProvider::FISERV_VALUELINK_KEY][FiservConfigProvider::IS_ACTIVE_KEY]) {
		
			$valuelinkCards = json_decode($shippingAssignment["valuelink_cards"] ?? "{}", true);
			$cardsDetails = array();

			foreach($valuelinkCards as $card)
			{
			    array_push($cardsDetails, ValuelinkQuoteRecord::createFromArray($card));
			}

			$this->quoteManager->RemoveValuelinkCardsFromQuote($cardsDetails, $quote);
		}
	}
}

