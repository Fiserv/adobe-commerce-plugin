<?php

namespace Fiserv\Payments\Block\Valuelink\Sales\Order;

use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Fiserv\Payments\Model\ValuelinkTransaction;

class ValuelinkCards extends \Magento\Framework\View\Element\Template
{
	private $valuelinkResource;
	
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		ValuelinkResource $valuelinkResource,
		array $data = []
	) {
		$this->valuelinkResource = $valuelinkResource;
		parent::__construct($context, $data);
		$this->_isScopePrivate = true;
	}

	public function getOrder()
	{
		return $this->getParentBlock()->getOrder();
	}

	public function getValuelinkCards()
	{
		$orderId = $this->getOrder()->getId();

		$cards = array();
		$rawCards = $this->valuelinkResource->getByOrderId($orderId);
		
		foreach ($rawCards as $card)
		{
			$primaryTransactionStates = ["AUTHORIZED", "CAPTURED"];
			if (in_array($card[ValuelinkTransaction::KEY_TRANSACTION_STATE], $primaryTransactionStates))
			{
				$_c = new \Magento\Framework\DataObject();

				$_c->setAmount($card[ValuelinkTransaction::KEY_AMOUNT]);
				
				array_push($cards, $_c);
			}
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
