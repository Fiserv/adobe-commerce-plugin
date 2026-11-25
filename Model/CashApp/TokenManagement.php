<?php
namespace Fiserv\Payments\Model\CashApp;

use Fiserv\Payments\Model\System\Utils\VaultPaymentTokenUtils;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;

/** Wallet token lookup only; Magento Vault and the repository plugin own persistence and retention. */
class TokenManagement
{
	private $tokenManagement;
	private $tokenUtils;

	public function __construct(
		PaymentTokenManagementInterface $tokenManagement,
		VaultPaymentTokenUtils $tokenUtils
	) {
		$this->tokenManagement = $tokenManagement;
		$this->tokenUtils = $tokenUtils;
	}

	public function getOriginal(int $customerId): ?array
	{
		return $this->tokenUtils->getFirstCashAppToken($customerId);
	}

	/** Usable when the public hash resolves to a visible, active token. */
	public function getAvailable(int $customerId, string $publicHash): ?PaymentTokenInterface
	{
		$token = $this->tokenManagement->getByPublicHash($publicHash, $customerId);
		return $token && $token->getIsVisible() && $token->getIsActive() ? $token : null;
	}
}
