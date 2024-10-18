<?php

namespace Fiserv\Payments\Block\Valuelink\Adminhtml\Sales\CreditMemo;

use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Fiserv\Payments\Model\Valuelink\Helper\CreditMemo\ValuelinkCreditMemoHelper;

class Totals extends \Fiserv\Payments\Block\Valuelink\Adminhtml\Sales\Order\Totals 
{	
	private $creditMemoHelper;

	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		ValuelinkResource $valuelinkResource,
		ValuelinkCreditMemoHelper $creditMemoHelper,
		\Magento\Framework\Registry $registry,
		\Magento\Sales\Helper\Admin $adminHelper,
		array $data = []
	) {
		$this->creditMemoHelper = $creditMemoHelper;
		parent::__construct($context, $valuelinkResource, $registry, $adminHelper, $data);
		$this->_isScopePrivate = true;
	}

	public function getCreditMemo()
	{
		return $this->getParentBlock()->getCreditmemo();
	}

	public function getValuelinkTotalForNewCreditMemo()
	{
		return $this->getCreditmemo()->getValuelinkAdjustment();
	}
}
