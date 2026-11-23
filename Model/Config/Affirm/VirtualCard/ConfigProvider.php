<?php
namespace Fiserv\Payments\Model\Config\Affirm\VirtualCard;

use Fiserv\Payments\Gateway\Config\Affirm\VirtualCard\Config as VirtualCardConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Affirm Virtual Card ConfigProvider has no renderer of its own: the Affirm renderer reads IS_ACTIVE_KEY from
 * window.checkoutConfig.payment.fiserv_affirm_virtual_card to decide which method code to place the order with.
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_affirm_virtual_card';
	const IS_ACTIVE_KEY = 'isActive';

	/**
	 * @var VirtualCardConfig
	 */
	private $config;

	/**
	 * @var StoreManagerInterface
	 */
	private $storeManager;

	public function __construct(
		VirtualCardConfig $config,
		StoreManagerInterface $storeManager
	) {
		$this->config = $config;
		$this->storeManager = $storeManager;
	}

	/**
	 * Retrieve Affirm Virtual Card checkout configuration
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
