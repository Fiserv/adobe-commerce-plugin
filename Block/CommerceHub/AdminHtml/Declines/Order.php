<?php
namespace Fiserv\Payments\Block\CommerceHub\AdminHtml\Declines;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

class Order extends Template
{
	const KEY_SUCCESSFUL = 'successful';
	const KEY_SUCCESSFUL_TXN = 'successful_txn';
	protected $urlBuilder;
	private $_pricingHelper;

	public function __construct(
		Template\Context $context,
		PricingHelper $pricingHelper,
		array $data = []
	) {
		$this->urlBuilder = $context->getUrlBuilder();
		parent::__construct($context, $data);
		$this->_pricingHelper = $pricingHelper;
	}

	public function getFailedOrder()
	{
		return $this->getData('order');
	}

	public function getFailedTransactionsForOrder()
	{
		return $this->getData('transactions');
	}

	public function getTotalPages()
	{
		return $this->getData('totalPages');
	}

	public function getAppliedGiftCardAmount()
	{
		return $this->getData('giftAmount');
	}

	public function formatPrice($amount)
	{
		return $this->_pricingHelper->currency($amount, true, false);
	}

	public function getExportUrl()
	{
		return $this->urlBuilder->getUrl('fiserv/declines/export');
	}
}

