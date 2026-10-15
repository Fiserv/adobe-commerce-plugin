<?php
namespace Fiserv\Payments\Model\Config\CashApp;

use Fiserv\Payments\Gateway\Config\CashApp\Config as CashAppConfig;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Fiserv\Payments\Model\CashApp\TokenStorage;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Supplies Cash App configuration to Magento checkout.
 */
class ConfigProvider implements ConfigProviderInterface
{
	public const CODE = 'fiserv_cashapp';
	public const HAS_SAVED_CASH_APP_KEY = 'hasSavedCashApp';

	private $cashAppConfig;
	private $commerceHubConfig;
	private $storeManager;
	private $customerSession;
	private $tokenStorage;

	public function __construct(
		CashAppConfig $cashAppConfig,
		CommerceHubConfig $commerceHubConfig,
		StoreManagerInterface $storeManager,
		CustomerSession $customerSession,
		TokenStorage $tokenStorage
	) {
		$this->cashAppConfig = $cashAppConfig;
		$this->commerceHubConfig = $commerceHubConfig;
		$this->storeManager = $storeManager;
		$this->customerSession = $customerSession;
		$this->tokenStorage = $tokenStorage;
	}

	public function getConfig()
	{
		$storeId = $this->storeManager->getStore()->getId();
		$customerId = $this->customerSession->isLoggedIn()
			? (int) $this->customerSession->getCustomerId()
			: 0;
		$hasSavedCashApp = $customerId > 0 && $this->tokenStorage->hasForCustomer($customerId);
		$canUseSavedCashApp = $hasSavedCashApp && $this->cashAppConfig->isActive($storeId)
			&& $this->cashAppConfig->isTokenizationEnabled($storeId)
			&& $this->tokenStorage->getReusableToken($customerId, [
				'website_id' => (int) $this->storeManager->getStore()->getWebsiteId(),
				'merchant_id' => (string) $this->commerceHubConfig->getMerchantId($storeId),
				'terminal_id' => (string) $this->commerceHubConfig->getTerminalId($storeId),
				'environment' => (string) $this->commerceHubConfig->getApiEnvironment($storeId),
			]) !== null;

		return [
			'payment' => [
				self::CODE => [
					'isActive' => (bool) $this->cashAppConfig->isActive($storeId),
					// Merchant preference only; not SDC eligibility or capability.
					'tokenizationEnabled' => (bool) $this->cashAppConfig->isTokenizationEnabled($storeId),
					self::HAS_SAVED_CASH_APP_KEY => $hasSavedCashApp,
					'canUseSavedCashApp' => $canUseSavedCashApp,
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

