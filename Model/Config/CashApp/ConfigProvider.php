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
		$customerId = $this->customerSession->isLoggedIn() ? (int) $this->customerSession->getCustomerId() : 0;
		$original = $this->tokenManagement->getOriginal($customerId);
		$saved = [];
		$notice = '';
		if ($original && $this->cashAppConfig->isActive($storeId)) {
			// Card-oriented visible-token queries exclude NULL expiry; Cash App has no gateway expiry.
			$token = $this->tokenManagement->getAvailable($customerId, (string) $original['public_hash'], (int) $storeId);
			$expired = $this->tokenManagement->isExpired($original['expires_at']);
			$notice = (string) __('Your saved Cash App Pay method is unavailable. Use one-time checkout; your original saved method will not be replaced.');
			if ($expired) {
				$notice = (string) __('Your saved Cash App Pay method has expired. Use one-time checkout; your original saved method will not be replaced.');
			}
			if ($token !== null) {
				$saved[] = ['publicHash' => $token->getPublicHash(), 'label' => (string) __('Cash App Pay')];
				$notice = '';
			}
		}

		return [
			'payment' => [
				self::CODE => [
					'canSave' => $customerId > 0 && $original === null,
					'savedTokens' => $saved,
					'savedTokenNotice' => $notice,
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

