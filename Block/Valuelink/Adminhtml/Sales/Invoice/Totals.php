<?php

namespace Fiserv\Payments\Block\Valuelink\Adminhtml\Sales\Invoice;

use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;
use Fiserv\Payments\Model\Valuelink\Helper\Invoice\ValuelinkInvoiceHelper;
use Fiserv\Payments\Model\ValuelinkTransaction;

class Totals extends \Fiserv\Payments\Block\Valuelink\Adminhtml\Sales\Order\Totals 
{	
	private $orderHelper;
	
	private $invoiceHelper;

	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		ValuelinkResource $valuelinkResource,
		ValuelinkOrderHelper $orderHelper,
		ValuelinkInvoiceHelper $invoiceHelper,
		\Magento\Framework\Registry $registry,
		\Magento\Sales\Helper\Admin $adminHelper,
		array $data = []
	) {
		$this->orderHelper = $orderHelper;
		$this->invoiceHelper = $invoiceHelper;
		parent::__construct($context, $valuelinkResource, $registry, $adminHelper, $data);
		$this->_isScopePrivate = true;
	}

	public function getInvoice()
	{
		return $this->getParentBlock()->getInvoice();
	}

	public function getInvoiceTotal()
	{
		return $this->getInvoice()->getGrandTotal();
	}

	public function getValuelinkTotalForNewInvoice()
	{
		$order = $this->getOrder();
		$remainingAuth = $this->orderHelper->getRemainingAuthAmount($order);
		return $this->invoiceHelper->getValuelinkInvoiceAmount($order, $this->getInvoice(), $remainingAuth);
	}

	public function getValuelinkTotalCapturedOnInvoice()
	{
		return $this->invoiceHelper->getCapturedValuelinkAmountByInvoice($this->getInvoice());
	}

	
}
