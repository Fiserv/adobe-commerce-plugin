<?php
namespace Fiserv\Payments\Model\Config\CashApp;

use Fiserv\Payments\Gateway\Config\CashApp\Config as CashAppConfig;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Fiserv\Payments\Model\CashApp\TokenManagement;

/**
 * Supplies Cash App configuration to Magento checkout.
 */
class ConfigProvider implements ConfigProviderInterface
{
	public const CODE = 'fiserv_cashapp';
	public const VAULT_CODE = 'fiserv_cashapp_vault';

	private $cashAppConfig;
	private $commerceHubConfig;
	private $storeManager;
	private $customerSession;
	private $tokenManagement;

	public function __construct(
		CashAppConfig $cashAppConfig,
		CommerceHubConfig $commerceHubConfig,
		StoreManagerInterface $storeManager,
		CustomerSession $customerSession,
		TokenManagement $tokenManagement
	) {
		$this->cashAppConfig = $cashAppConfig;
		$this->commerceHubConfig = $commerceHubConfig;
		$this->storeManager = $storeManager;
		$this->customerSession = $customerSession;
		$this->tokenManagement = $tokenManagement;
	}

	public function getConfig()
	{
		$storeId = $this->storeManager->getStore()->getId();
		$customerId = $this->customerSession->isLoggedIn() ? $this->customerSession->getCustomerId() : 0;
		$original = $this->tokenManagement->getOriginal($customerId);
		$available = $original && $original['is_active'] && $original['is_visible'];

		return [
			'payment' => [
				self::CODE => [
					'canSave' => $customerId > 0 && $original === null,
					'hasSavedToken' => $original !== null,
					'savedTokenHash' => $available ? $original['public_hash'] : '',
					'isActive' => $this->cashAppConfig->isActive($storeId),
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

