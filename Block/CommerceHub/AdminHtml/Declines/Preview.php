<?php
namespace Fiserv\Payments\Block\CommerceHub\AdminHtml\Declines;

use Magento\Framework\View\Element\Template;

class Preview extends Template
{
	public function __construct(
		Template\Context $context,
		array $data = []
	) {
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

	public function getAllOrderState()
	{
		return $this->getData('order_state');
	}
}

