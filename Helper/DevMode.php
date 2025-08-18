<?php

namespace Fiserv\Payments\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class DevMode extends AbstractHelper
{
	const XML_PATH_DEVELOPER_MODE_ACTIVE = 'payment/fiserv_commercehub/developer_mode_active';

	/**
	 * @var ScopeConfigInterface
	 */
	protected $scopeConfig;

	/**
	 * DevMode constructor.
	 * @param Context $context
	 * @param ScopeConfigInterface $scopeConfig
	 */
	public function __construct(
		Context $context,
		ScopeConfigInterface $scopeConfig
	) {
		$this->scopeConfig = $scopeConfig;
		parent::__construct($context);
	}

	/**
	 * Check if developer mode is active.
	 *
	 * @return bool
	 */
	public function isDeveloperModeActive()
	{
		return $this->scopeConfig->isSetFlag(
			self::XML_PATH_DEVELOPER_MODE_ACTIVE,
			\Magento\Store\Model\ScopeInterface::SCOPE_STORE
		);
	}
}