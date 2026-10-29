<?php
namespace Fiserv\Payments\Gateway\Config\Affirm\VirtualCard;

use Fiserv\Payments\Gateway\Config\Affirm\Config as AffirmConfig;

/**
 * Affirm Virtual Card config.
 * Virtual Card is a sub-feature of Affirm:
 * it is only active when Affirm AND Virtual Card are both enabled.
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	const CODE = 'fiserv_affirm_virtual_card';
	private $affirmConfig;

	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		AffirmConfig $affirmConfig
	) {
		parent::__construct($scopeConfig, self::CODE, self::DEFAULT_PATH_PATTERN);
		$this->affirmConfig = $affirmConfig;
	}

	/**
	 * Is Affirm Virtual Card active
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isActive($storeId = null): bool
	{
		return $this->affirmConfig->isActive($storeId) && $this->affirmConfig->isVirtualCardActive($storeId);
	}

	/**
	 * Get payment action (shared with Affirm; no separate Virtual Card charge type)
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getPaymentAction($storeId = null): string
	{
		return $this->affirmConfig->getPaymentAction($storeId);
	}
}
