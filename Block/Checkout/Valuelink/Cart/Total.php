<?php

namespace Fiserv\Payments\Block\Checkout\Valuelink\Cart;

use Magento\Checkout\Helper\Data;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\ConfigInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Fiserv\Payments\Helper\Valuelink\DataHelper;

class Total extends \Magento\Checkout\Block\Total\DefaultTotal
{
    /**
     * @var string
     */
    protected $_template = 'Fiserv_Payments::cart/valuelink/total.phtml';

    /**
     * @var \Fiserv\Payments\Helper\Valuelink\DataHelper
     */
    protected $valuelinkDataHelper;


    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param ConfigInterface $salesConfig
     * @param DataHelper $valuelinkDataHelper
     * @param array $layoutProcessors
     * @param array $data
     * @param Data|null $checkoutHelper
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        ConfigInterface $salesConfig,
        DataHelper $valuelinkDataHelper,
		array $layoutProcessors = [],
        array $data = [],
        Data $checkoutHelper = null
    ) {
        $data['checkoutHelper'] = $checkoutHelper ?? ObjectManager::getInstance()->get(Data::class);
        $this->valuelinkDataHelper = $valuelinkDataHelper;
        parent::__construct($context, $customerSession, $checkoutSession, $salesConfig, $layoutProcessors, $data);
		$this->_isScopePrivate = true;
    }

    /**
     * Get sales quote
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function getQuote()
    {
        return $this->_checkoutSession->getQuote();
    }

    /**
     * Get valuelink card list from quote
     *
     * @return mixed
     */
    public function getQuoteGiftCards()
    {
		return $this->valuelinkDataHelper->getValuelinkRecordsFromQuote($this->getQuote());
    }
}
