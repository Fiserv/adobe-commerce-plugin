<?php
declare(strict_types=1);
namespace Fiserv\Payments\Service;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\SubscriptionOrder\SubscriptionOrderRepository;
use Fiserv\Payments\Model\System\Utils\PaymentTokenUtil;
use Fiserv\Payments\Service\VaultTokenDetailsParser;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;

class SubscriptionPaymentMethodUpdater
{
	public function __construct(
		private readonly SubscriptionOrderRepository $subscriptionRepo,
		private readonly PaymentTokenManagementInterface $tokenManagement,
		private readonly MultiLevelLogger $logger,
		private readonly VaultTokenDetailsParser $tokenDetailsParser
	) {}

	/**
	 * Update the payment method for the entire subscription chain going forward.
	 * This updates ONLY the chain head row (sequence = FIRST). Historical rows are not modified.
	 *
	 * Returns the masked card string (e.g. "************1111") for display in the UI.
	 *
	 * @throws \Magento\Framework\Exception\NoSuchEntityException
	 * @throws \Magento\Framework\Exception\CouldNotSaveException
	 * @throws \RuntimeException
	 */
	public function updateChainPaymentToken(int $customerId, string $customerEmail, int $subscriptionId, string $publicHash): string
	{
		$publicHash = trim($publicHash);
		if ($subscriptionId <= 0 || $publicHash === '') {
			throw new \RuntimeException('Missing parameters');
		}

		$clicked = $this->subscriptionRepo->getById($subscriptionId);

		// Ownership check (match your Cancel controller behavior)
		$ownerId = $clicked->getCustomerId();
		$ownerEmail = (string)$clicked->getCustomerEmail();

		if ($ownerId !== null && (int)$ownerId !== $customerId) {
			throw new \RuntimeException('Not authorized');
		}
		if ($ownerId === null && $ownerEmail !== '' && $customerEmail !== '' && strcasecmp($ownerEmail, $customerEmail) !== 0) {
			throw new \RuntimeException('Not authorized');
		}

		// Resolve chain key
		$chainKey = (string)($clicked->getOriginalOrderIncrement() ?: $clicked->getOrderIncrementId());
		if ($chainKey === '') {
			throw new \RuntimeException('Invalid subscription chain');
		}

		// Find chain head (FIRST)
		$head = $this->subscriptionRepo->getChainHeadByOriginalIncrement($chainKey) ?: $clicked;

		// Only allow update if chain head is active (recommended)
		$status = strtolower((string)$head->getStatus());
		if ($status !== 'active') {
			throw new \RuntimeException('Subscription is not active');
		}

		// Validate Vault token belongs to this customer and is active
		/** @var PaymentTokenInterface|null $token */
		$token = $this->tokenManagement->getByPublicHash($publicHash, $customerId);
		if (!$token || !$token->getIsActive()) {
			throw new \RuntimeException('Invalid token');
		}

		// Extract the raw TransArmor token from the vault gateway_token column.
		// gateway_token is persisted as "tokenData$$TS$$=timestamp" — we strip the suffix
		// so that payment_token stays consistent with what SubscriptionProcessor queries against.
		$rawGatewayToken = PaymentTokenUtil::getTokenDataFromPersistenceFormat(
			(string)$token->getGatewayToken()
		);
		if ($rawGatewayToken === '') {
			throw new \RuntimeException('Could not resolve gateway token from vault record');
		}

		// Update head ONLY
		$head->setPaymentToken($rawGatewayToken);

		// Optional: update expiry on head for display
		$this->tryUpdateExpiryFromVault($head, $token);

		// Persist the masked card so both customer and admin UIs reflect the change
		// without requiring a full page reload. Cleared automatically after the next renewal.
		$maskedCard = $this->getMaskedCardFromToken($token);
		$head->setChangePaymentCard($maskedCard ?: null);

		$this->subscriptionRepo->save($head);

		$this->logger->logInfo(1, 'Payment card updated for subscription chain',
			sprintf(
				'[Order ID: %s] Customer ID: %d — new card: %s',
				$head->getOrderIncrementId(),
				$customerId,
				$maskedCard ?: 'unknown'
			)
		);

		return $maskedCard;
	}

	/**
	 * Extracts the masked card number (e.g. ************1111) from the vault token's
	 * stored JSON details. Returns an empty string if it cannot be resolved.
	 */
	private function getMaskedCardFromToken(PaymentTokenInterface $token): string
	{
		try {
			return $this->tokenDetailsParser->extractMaskedCC($token->getTokenDetails());
		} catch (\Throwable) {
			return '';
		}
	}

	private function tryUpdateExpiryFromVault($subscription, PaymentTokenInterface $token): void
	{
		try {
			[$expirationMonth, $expirationYear] = $this->tokenDetailsParser->extractExpiry($token->getTokenDetails());
			if ($expirationMonth !== null) {
				$subscription->setExpirationMonth($expirationMonth);
			}
			if ($expirationYear !== null) {
				$subscription->setExpirationYear($expirationYear);
			}
		} catch (\Throwable $e) {
			$this->logger->logDebug(2, 'UpdatePayment: failed to parse vault expiry: ' . $e->getMessage());
		}
	}
}