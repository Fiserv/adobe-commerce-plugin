<?php

namespace Fiserv\Payments\Block\Valuelink\Sales\CreditMemo;

use Fiserv\Payments\Model\ValuelinkTransaction;
use Fiserv\Payments\Model\Valuelink\Helper\CreditMemo\ValuelinkCreditMemoHelper;
use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;

class ValuelinkCards extends \Magento\Framework\View\Element\Template
{
	private $creditMemoHelper;

	private $orderHelper;
	
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		ValuelinkCreditMemoHelper $creditMemoHelper,
		ValuelinkOrderHelper $orderHelper,
		array $data = []
	) {
		$this->creditMemoHelper = $creditMemoHelper;
		$this->orderHelper = $orderHelper;
		parent::__construct($context, $data);
		$this->_isScopePrivate = true;
	}

	public function getOrder()
	{
		return $this->getParentBlock()->getOrder();
	}

	public function getCreditMemo()
	{
		return $this->getParentBlock()->getCreditmemo();
	}
	
	public function getValuelinkTotalForCreditMemo()
	{
		$primaryTxns = $this->orderHelper->getPrimaryValuelinkTransactionsByOrder($this->getOrder());
		return count($primaryTxns) > 0 ? $this->creditMemoHelper->getValuelinkAmountAppliedToCreditMemo($this->getCreditMemo()) : 0;	
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
