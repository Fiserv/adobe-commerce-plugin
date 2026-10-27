<?php
namespace Fiserv\Payments\Model\CashApp;

use Fiserv\Payments\Gateway\Config\CashApp\Config;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Fiserv\Payments\Model\System\Utils\VaultPaymentTokenUtils;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;

/** Wallet eligibility only; Magento Vault and the repository plugin own persistence and retention. */
class TokenManagement
{
	private $config;
	private $commerceHubConfig;
	private $tokenManagement;
	private $tokenUtils;
	private $storeManager;

	public function __construct(
		Config $config,
		CommerceHubConfig $commerceHubConfig,
		PaymentTokenManagementInterface $tokenManagement,
		VaultPaymentTokenUtils $tokenUtils,
		StoreManagerInterface $storeManager
	) {
		$this->config = $config;
		$this->commerceHubConfig = $commerceHubConfig;
		$this->tokenManagement = $tokenManagement;
		$this->tokenUtils = $tokenUtils;
		$this->storeManager = $storeManager;
	}

	public function getOriginal(int $customerId): ?array
	{
		return $this->tokenUtils->getFirstCashAppToken($customerId);
	}

	public function isExpired(?string $expiresAt): bool
	{
		return $this->tokenUtils->isCashAppTokenExpired($expiresAt);
	}

	public function getAvailable(int $customerId, string $publicHash, int $storeId): ?PaymentTokenInterface
	{
		if ($customerId <= 0 || $publicHash === '' || !$this->config->isActive($storeId)) {
			return null;
		}

		$original = $this->getOriginal($customerId);
		$token = $this->tokenManagement->getByPublicHash($publicHash, $customerId);
		if (!$original || !$token || (int) $token->getEntityId() !== (int) $original['entity_id'] ||
			(int) $token->getCustomerId() !== $customerId || $token->getPaymentMethodCode() !== Config::CODE ||
			$token->getType() !== 'cashapp' || !$token->getIsActive() || !$token->getIsVisible() ||
			!$token->getGatewayToken() || $this->isExpired($token->getExpiresAt())) {
			return null;
		}

		$details = json_decode($token->getTokenDetails() ?: '{}', true);
		if (!is_array($details) || ($details['tokenSource'] ?? '') !== 'FISERV_SECURE_VAULT' ||
			(int) ($details['websiteId'] ?? 0) !== (int) $this->storeManager->getStore($storeId)->getWebsiteId() ||
			($details['merchantId'] ?? '') !== (string) $this->commerceHubConfig->getMerchantId($storeId) ||
			($details['terminalId'] ?? '') !== (string) $this->commerceHubConfig->getTerminalId($storeId) ||
			($details['environment'] ?? '') !== (string) $this->commerceHubConfig->getApiEnvironment($storeId)) {
			return null;
		}

		return $token;
	}
}
