<?php
namespace Fiserv\Payments\Model\Klarna;

use Fiserv\Payments\Gateway\Config\Klarna\Config;
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
		return $this->tokenUtils->getFirstKlarnaToken($customerId);
	}

	public function isExpired(?string $expiresAt): bool
	{
		return $this->tokenUtils->isKlarnaTokenExpired($expiresAt);
	}

	public function getAvailable(int $customerId, string $publicHash, int $storeId): ?PaymentTokenInterface
	{
		if ($customerId <= 0 || $publicHash === '' || !$this->config->isActive($storeId)) {
			return null;
		}

		$original = $this->getOriginal($customerId);
		$token = $this->tokenManagement->getByPublicHash($publicHash, $customerId);
		if (!$original || !$token || $token->getEntityId() != $original['entity_id'] ||
			$token->getCustomerId() != $customerId || $token->getPaymentMethodCode() !== Config::CODE ||
			$token->getType() !== 'klarna' || !$token->getIsActive() || !$token->getIsVisible() ||
			!$token->getGatewayToken() || $this->isExpired($token->getExpiresAt())) {
			return null;
		}

		$details = json_decode($token->getTokenDetails(), true);
		if ($details['websiteId'] != $this->storeManager->getStore($storeId)->getWebsiteId() ||
			$details['merchantId'] !== $this->commerceHubConfig->getMerchantId($storeId) ||
			$details['terminalId'] !== $this->commerceHubConfig->getTerminalId($storeId) ||
			$details['environment'] !== $this->commerceHubConfig->getApiEnvironment($storeId)) {
			return null;
		}

		return $token;
	}
}
