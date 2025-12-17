<?php
declare(strict_types=1);
namespace Fiserv\Payments\Service;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\SubscriptionOrder\SubscriptionOrderRepository;
use Fiserv\Payments\Model\System\Utils\PaymentTokenUtil;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;

class SubscriptionPaymentMethodUpdater
{
	public function __construct(
		private readonly SubscriptionOrderRepository $subscriptionRepo,
		private readonly PaymentTokenManagementInterface $tokenManagement,
		private readonly MultiLevelLogger $logger
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
		$head->setPendingCardLabel($maskedCard ?: null);

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
			$detailsRaw = $token->getTokenDetails();
			$details = is_string($detailsRaw)
				? @json_decode($detailsRaw, true)
				: (is_array($detailsRaw) ? $detailsRaw : null);

			if (!is_array($details)) {
				return '';
			}

			// maskedCC is stored by VaultDetailsHandler as e.g. "************1111"
			return (string)($details['maskedCC'] ?? '');
		} catch (\Throwable) {
			return '';
		}
	}

	private function tryUpdateExpiryFromVault($subscription, PaymentTokenInterface $token): void
	{
		try {
			$detailsRaw = $token->getTokenDetails();
			$details = is_string($detailsRaw) ? @json_decode($detailsRaw, true) : (is_array($detailsRaw) ? $detailsRaw : null);
			if (!is_array($details)) {
				return;
			}

			$expirationMonth = null;
			$expirationYear = null;

			// VaultDetailsHandler stores expiry as a combined "MM/YYYY" string in expirationDate.
			// Fall back to separate month/year keys if the combined field is absent.
			$combinedDate = $details['expirationDate'] ?? $details['expiration_date'] ?? null;
			if (is_string($combinedDate) && $combinedDate !== '') {
				$dateParts = preg_split('/[\/\-]/', $combinedDate);
				if (count($dateParts) >= 2) {
					$expirationMonth = $dateParts[0];
					$expirationYear = $dateParts[1];
				}
			}

			// Separate key fallback (other token sources)
			if ($expirationMonth === null) {
				$expirationMonth = $details['expirationMonth'] ?? $details['expMonth'] ?? $details['expiration_month'] ?? $details['exp_month'] ?? null;
			}
			if ($expirationYear === null) {
				$expirationYear = $details['expirationYear'] ?? $details['expYear'] ?? $details['expiration_year'] ?? $details['exp_year'] ?? null;
			}

			if ($expirationMonth !== null) {
				$subscription->setExpirationMonth(str_pad((string)((int)$expirationMonth), 2, '0', STR_PAD_LEFT));
			}
			if ($expirationYear !== null) {
				$subscription->setExpirationYear((string)$expirationYear);
			}
		} catch (\Throwable $e) {
			$this->logger->logDebug(2, 'UpdatePayment: failed to parse vault expiry: ' . $e->getMessage());
		}
	}
}