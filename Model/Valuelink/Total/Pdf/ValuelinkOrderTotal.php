<?php

namespace Fiserv\Payments\Model\Valuelink\Total\Pdf;

use Magento\Sales\Model\Order\Pdf\Total\DefaultTotal;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;
use Fiserv\Payments\Gateway\Config\Valuelink\Config;
use Fiserv\Payments\Model\Valuelink\Helper\Invoice\ValuelinkInvoiceHelper;
use Fiserv\Payments\Model\Valuelink\Helper\CreditMemo\ValuelinkCreditMemoHelper;

class ValuelinkOrderTotal extends DefaultTotal
{
	private const DEFAULT_FONT_SIZE = 7;

	private $config;

	private $valuelinkInvoiceHelper;

	private $creditMemoHelper;

	public function __construct(
		TaxHelper $taxHelper,
		Calculation $taxCalculation,
		CollectionFactory $ordersFactory,
		Config $config,
		ValuelinkInvoiceHelper $valuelinkInvoiceHelper,
		ValuelinkCreditMemoHelper $creditMemoHelper,
		array $data = []
	) {
		parent::__construct($taxHelper, $taxCalculation, $ordersFactory, $data);
		$this->config = $config;
		$this->valuelinkInvoiceHelper = $valuelinkInvoiceHelper;
		$this->creditMemoHelper = $creditMemoHelper;
	}

	public function canDisplay()
	{
		$source = $this->getSource();
		$total = 0;
		if ($source instanceof \Magento\Sales\Model\Order\Invoice)
		{
			$total = $this->valuelinkInvoiceHelper->getCapturedValuelinkAmountByInvoice($source);
		}
		elseif ($source instanceof \Magento\Sales\Model\Order\Creditmemo)
		{
			$total = $this->creditMemoHelper->getValuelinkAmountAppliedToCreditMemo($source);
		}
		return $total !== 0;
	}
	
	public function getTotalsForDisplay()
	{
		$order = $this->getOrder();
		$source = $this->getSource();
		if ($source instanceof \Magento\Sales\Model\Order\Invoice)
		{
			$total = $this->valuelinkInvoiceHelper->getCapturedValuelinkAmountByInvoice($source);
		}
		elseif ($source instanceof \Magento\Sales\Model\Order\Creditmemo)
		{
			$total = $this->creditMemoHelper->getValuelinkAmountAppliedToCreditMemo($source);
		}
		return [[
			'amount' => $this->getAmountPrefix() . $order->formatPriceTxt($total),
			'label' => __($this->config->getValuelinkTitle()) . ':',
			'font_size' => $this->getFontSize() ?? self::DEFAULT_FONT_SIZE
		]];
	}
}
