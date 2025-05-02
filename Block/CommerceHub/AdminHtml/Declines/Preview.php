<?php
namespace Fiserv\Payments\Block\CommerceHub\AdminHtml\Declines;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

class Preview extends Template
{
	private $pricingHelper;

	public function __construct(
		Template\Context $context,
		PricingHelper $pricingHelper,
		array $data = []
	) {
		$this->pricingHelper = $pricingHelper;
		parent::__construct($context, $data);
	}

	public function getOrders()
	{
		return $this->getData('orders');
	}

	public function getTotalPages()
	{
		return $this->getData('totalPages');
	}

	public function getAllApprovalStatus()
	{
		return $this->getData('approval_status');
	}

	public function formatPrice($amount)
	{
		return $this->pricingHelper->currency($amount, true, false);
	}
}

