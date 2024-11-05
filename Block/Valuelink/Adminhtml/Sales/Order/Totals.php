<?php

namespace Fiserv\Payments\Block\Valuelink\Adminhtml\Sales\Order;

use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;
use Fiserv\Payments\Model\ValuelinkTransaction;

class Totals extends \Magento\Sales\Block\Adminhtml\Order\Totals\Item
{
	private $orderHelper;

	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		ValuelinkOrderHelper $orderHelper,
		\Magento\Framework\Registry $registry,
		\Magento\Sales\Helper\Admin $adminHelper,
		array $data = []
	) {
		$this->orderHelper = $orderHelper;
		parent::__construct($context, $registry, $adminHelper, $data);
		$this->_isScopePrivate = true;
	}

	public function getOrder()
	{
		return $this->getParentBlock()->getOrder();
	}

	public function getValuelinkCards()
	{
		$cards = array();
		$rawCards = $this->orderHelper->getPrimaryValuelinkTransactionsByOrder($this->getOrder());

		foreach ($rawCards as $card)
		{
			$_c = new \Magento\Framework\DataObject();
			$_c->setAmount($card[ValuelinkTransaction::KEY_AMOUNT]);
				
			array_push($cards, $_c);
		}

		return $cards;
	}
}
