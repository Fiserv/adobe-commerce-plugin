<?php
namespace Fiserv\Payments\Gateway\Config\PayPal\PayLater;

use Fiserv\Payments\Gateway\Config\PayPal\Config as PayPalConfig;

/**
 * PayPal Pay Later config.
 * Pay Later is a sub-feature of PayPal:
 * it is only active when PayPal AND Pay Later are both enabled.
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	const CODE = 'fiserv_paypal_paylater';
	private $paypalConfig;

	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		PayPalConfig $paypalConfig
	) {
		parent::__construct($scopeConfig, self::CODE, self::DEFAULT_PATH_PATTERN);
		$this->paypalConfig = $paypalConfig;
	}

	/**
	 * Is PayPal Pay Later active
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isActive($storeId = null): bool
	{
		return $this->paypalConfig->isActive($storeId) && $this->paypalConfig->isPayLaterActive($storeId);
	}
}
