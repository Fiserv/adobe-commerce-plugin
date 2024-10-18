<?php

namespace Fiserv\Payments\Model\Valuelink\Total\CreditMemo;

use Fiserv\Payments\Model\Valuelink\Helper\CreditMemo\ValuelinkCreditMemoHelper;

class ValuelinkCard extends \Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal
{
	private $creditMemoHelper;

	public function __construct(
		ValuelinkCreditMemoHelper $creditMemoHelper
	){
		$this->creditMemoHelper = $creditMemoHelper;
	}
		
	public function collect(\Magento\Sales\Model\Order\Creditmemo $creditMemo)	
	{
		$adjustment = $this->creditMemoHelper->getCreditMemoAdjustment($creditMemo);

		$creditMemo->setGrandTotal($creditMemo->getGrandTotal() - $adjustment);
		$creditMemo->setBaseGrandTotal($creditMemo->getBaseGrandTotal() - $adjustment);

		return $this;
	}
}
