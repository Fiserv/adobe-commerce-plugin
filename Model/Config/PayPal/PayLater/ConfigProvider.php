<?php
namespace Fiserv\Payments\Model\Config\PayPal\PayLater;

use Fiserv\Payments\Gateway\Config\PayPal\PayLater\Config as PayLaterConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * PayPal Pay Later ConfigProvider has no renderer of its own: the PayPal renderer reads IS_ACTIVE_KEY from
 * window.checkoutConfig.payment.fiserv_paypal_paylater to decide whether to render the Pay Later button, and
 * places the order under this method code when that button is used.
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_paypal_paylater';
	const IS_ACTIVE_KEY = 'isActive';

	/**
	 * @var PayLaterConfig
	 */
	private $config;

	/**
	 * @var StoreManagerInterface
	 */
	private $storeManager;

	public function __construct(
		PayLaterConfig $config,
		StoreManagerInterface $storeManager
	) {
		$this->config = $config;
		$this->storeManager = $storeManager;
	}

	/**
	 * Retrieve PayPal Pay Later checkout configuration
	 *
	 * @return array
	 */
	public function getConfig()
	{
		$storeId = $this->storeManager->getStore()->getId();

		$config = [
			self::IS_ACTIVE_KEY => $this->config->isActive($storeId),
		];
		return ['payment' => [self::CODE => $config]];
	}
}
