<?php

namespace Fiserv\Payments\Block\Valuelink\Sales\Order;

use Fiserv\Payments\Model\ValuelinkTransaction;
use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;

class ValuelinkCards extends \Magento\Framework\View\Element\Template
{
	private $orderHelper;

	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		ValuelinkOrderHelper $orderHelper,
		array $data = []
	) {
		$this->orderHelper = $orderHelper;
		parent::__construct($context, $data);
		$this->_isScopePrivate = true;
	}

	public function getOrder()
	{
		return $this->getParentBlock()->getOrder();
	}

	public function getValuelinkCards()
	{
		$order = $this->getOrder();
		$cards = array();
		
		$primaryTxns = $this->orderHelper->getPrimaryValuelinkTransactionsByOrder($order);	
		foreach ($primaryTxns as $card)
		{
			$_c = new \Magento\Framework\DataObject();
			$_c->setAmount($card[ValuelinkTransaction::KEY_AMOUNT]);

			array_push($cards, $_c);
		}

		return $cards;
	}

	/*
	 * Initialize giftcard order total
	 *
	 * @return $this
		* */
	public function initTotals()
	{
		$total = new \Magento\Framework\DataObject(
			[
				'code' => $this->getNameInLayout(),
				'block_name' => $this->getNameInLayout(),
				'area' => $this->getArea(),
			]
		);
		$this->getParentBlock()->addTotalBefore($total, ['customerbalance', 'grand_total']);
		
		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getLabelProperties()
	{
		return $this->getParentBlock()->getLabelProperties();
	}

	/**
	 * @return mixed
	 */
	public function getValueProperties()
	{
		return $this->getParentBlock()->getValueProperties();
	}
}
