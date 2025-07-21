<?php
namespace Fiserv\Payments\Block\CommerceHub\AdminHtml\Declines;

use Magento\Framework\View\Element\Template;

class Preview extends Template
{
	protected $urlBuilder;

	public function __construct(
		Template\Context $context,
		array $data = []
	) {
		$this->urlBuilder = $context->getUrlBuilder();
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
	
	public function getExportUrl()
	{
		return $this->urlBuilder->getUrl('fiserv/declines/export');
	}
}

