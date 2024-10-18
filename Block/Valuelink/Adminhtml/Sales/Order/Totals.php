<?php

namespace Fiserv\Payments\Block\Valuelink\Adminhtml\Sales\Order;

use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Fiserv\Payments\Model\ValuelinkTransaction;

class Totals extends \Magento\Sales\Block\Adminhtml\Order\Totals\Item
{
	private $valuelinkResource;

	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		ValuelinkResource $valuelinkResource,
		\Magento\Framework\Registry $registry,
		\Magento\Sales\Helper\Admin $adminHelper,
		array $data = []
	) {
		$this->valuelinkResource = $valuelinkResource;
		parent::__construct($context, $registry, $adminHelper, $data);
		$this->_isScopePrivate = true;
	}

	public function getOrder()
	{
		return $this->getParentBlock()->getOrder();
	}

	public function getValuelinkCards()
	{
		$orderIncrementId = $this->getOrder()->getIncrementId();

		$cards = array();
		$rawCards = $this->valuelinkResource->getByOrderIncrementId($orderIncrementId);
		
		foreach ($rawCards as $card)
		{
			$primaryTransactionStates = ["AUTHORIZED", "CAPTURED"];
			if (in_array($card[ValuelinkTransaction::KEY_TRANSACTION_STATE], $primaryTransactionStates) && $card[ValuelinkTransaction::KEY_PARENT_TRANSACTION_ID] == null)
			{
				$_c = new \Magento\Framework\DataObject();

				$_c->setAmount($card[ValuelinkTransaction::KEY_AMOUNT]);
				
				array_push($cards, $_c);
			}
		}

		return $cards;
	}
}
