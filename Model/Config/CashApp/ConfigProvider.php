<?php
namespace Fiserv\Payments\Model\Config\CashApp;

use Fiserv\Payments\Gateway\Config\CashApp\Config as CashAppConfig;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Supplies Cash App configuration to Magento checkout.
 */
class ConfigProvider implements ConfigProviderInterface
{
	public const CODE = 'fiserv_cashapp';

	private $cashAppConfig;
	private $commerceHubConfig;
	private $storeManager;

	public function __construct(
		CashAppConfig $cashAppConfig,
		CommerceHubConfig $commerceHubConfig,
		StoreManagerInterface $storeManager
	) {
		$this->cashAppConfig = $cashAppConfig;
		$this->commerceHubConfig = $commerceHubConfig;
		$this->storeManager = $storeManager;
	}

	public function getConfig()
	{
		$storeId = $this->storeManager->getStore()->getId();

		return [
			'payment' => [
				self::CODE => [
					'isActive' => (bool) $this->cashAppConfig->isActive($storeId),
					'payment_action' => $this->cashAppConfig->getPaymentAction($storeId),
					'apiKey' => $this->commerceHubConfig->getApiKey($storeId),
					'merchantId' => $this->commerceHubConfig->getMerchantId($storeId),
					'terminalId' => $this->commerceHubConfig->getTerminalId($storeId),
					'environment' => $this->commerceHubConfig->getApiEnvironment($storeId),
					'buttonConfig' => [
						'theme' => $this->cashAppConfig->getButtonTheme($storeId),
						'shape' => $this->cashAppConfig->getButtonShape($storeId),
						'size' => $this->cashAppConfig->getButtonSize($storeId),
						'width' => $this->cashAppConfig->getButtonWidth($storeId),
					],
				],
			],
		];
	}
}

