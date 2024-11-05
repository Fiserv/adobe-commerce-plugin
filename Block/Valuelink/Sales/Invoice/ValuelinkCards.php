<?php

namespace Fiserv\Payments\Block\Valuelink\Sales\Invoice;

use Fiserv\Payments\Model\ValuelinkTransaction;
use Fiserv\Payments\Model\Valuelink\Helper\Invoice\ValuelinkInvoiceHelper;
use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;

class ValuelinkCards extends \Magento\Framework\View\Element\Template
{
	private $invoiceHelper;

	private $orderHelper;
	
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		ValuelinkInvoiceHelper $invoiceHelper,
		ValuelinkOrderHelper $orderHelper,
		array $data = []
	) {
		$this->invoiceHelper = $invoiceHelper;
		$this->orderHelper = $orderHelper;
		parent::__construct($context, $data);
		$this->_isScopePrivate = true;
	}

	public function getOrder()
	{
		return $this->getParentBlock()->getOrder();
	}
	
	public function getInvoice()
	{
		return $this->getParentBlock()->getInvoice();
	}

	public function getInvoiceValuelinkCards()
	{
		$invoice = $this->getInvoice();
		$cards = array();

		$primaryTxns = $this->invoiceHelper->getPrimaryValuelinkTransactionsByInvoice($invoice);
		foreach ($primaryTxns as $card)
		{
			$_c = new \Magento\Framework\DataObject();
			$_c->setAmount($card[ValuelinkTransaction::KEY_AMOUNT]);
				
			array_push($cards, $_c);
		}

		// If VL sale txns exist on the order,
		// account for VL sale txns created with order and applied to invoice
		if ($this->orderHelper->sumOfValuelinkSales($this->getOrder()) > 0)
		{
			$saleAmt = $this->invoiceHelper->getValuelinkSaleBalanceAppliedToInvoice($invoice);
			if ($saleAmt > 0)
			{
				$_c = new \Magento\Framework\DataObject();
				$_c->setAmount($saleAmt);

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
