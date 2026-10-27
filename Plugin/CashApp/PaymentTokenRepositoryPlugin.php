<?php
namespace Fiserv\Payments\Plugin\CashApp;

use Fiserv\Payments\Model\System\Utils\VaultPaymentTokenUtils;
use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class PaymentTokenRepositoryPlugin
{
	private $vaultPaymentTokenUtils;

	public function __construct(VaultPaymentTokenUtils $vaultPaymentTokenUtils)
	{
		$this->vaultPaymentTokenUtils = $vaultPaymentTokenUtils;
	}

	/** Keep native Vault persistence/linking, but serialize first saves for the same customer. */
	public function aroundSave(
		PaymentTokenRepositoryInterface $subject,
		callable $proceed,
		PaymentTokenInterface $token
	) {
		$persisted = $token->getEntityId() ? $subject->getById($token->getEntityId()) : null;
		$isStoredCashApp = $persisted && $persisted->getPaymentMethodCode() === 'fiserv_cashapp';
		if (!$isStoredCashApp && $token->getPaymentMethodCode() !== 'fiserv_cashapp') {
			return $proceed($token);
		}
		$customerId = (int) ($isStoredCashApp ? $persisted->getCustomerId() : $token->getCustomerId());
		if (!$customerId) {
			throw new LocalizedException(__('Sign in before saving Cash App Pay.'));
		}
		$connection = $this->vaultPaymentTokenUtils->getConnection();
		$connection->beginTransaction();
		try {
			// Lock an existing row even when this customer's first Vault token does not exist yet.
			$customer = $connection->fetchOne($connection->select()
				->from($this->vaultPaymentTokenUtils->getTable('customer_entity'), ['entity_id'])
				->where('entity_id = ?', $customerId)->forUpdate(true));
			if (!$customer) {
				throw new LocalizedException(__('The Cash App Pay customer no longer exists.'));
			}
			$existing = $this->vaultPaymentTokenUtils->getFirstCashAppToken($customerId, true);
			if ($existing) {
				// Mutate the original object: native management uses its ID to link the order payment.
				foreach ([
					'EntityId' => 'entity_id', 'CustomerId' => 'customer_id',
					'PublicHash' => 'public_hash', 'PaymentMethodCode' => 'payment_method_code',
					'Type' => 'type', 'GatewayToken' => 'gateway_token', 'TokenDetails' => 'details',
					'CreatedAt' => 'created_at', 'ExpiresAt' => 'expires_at',
					'IsActive' => 'is_active', 'IsVisible' => 'is_visible',
				] as $setter => $column) {
					$token->{'set' . $setter}($existing[$column]);
				}
				$result = $token;
			} else {
				$result = $proceed($token);
			}
			$connection->commit();
			return $result;
		} catch (\Throwable $exception) {
			$connection->rollBack();
			throw $exception;
		}
	}

	/** The first wallet record is retained; normal card deletion is unchanged. */
	public function aroundDelete(
		PaymentTokenRepositoryInterface $subject,
		callable $proceed,
		PaymentTokenInterface $token
	) {
		$stored = $subject->getById($token->getEntityId());
		if ($stored->getPaymentMethodCode() === 'fiserv_cashapp') {
			throw new LocalizedException(__('The saved Cash App Pay method cannot be removed or replaced.'));
		}
		return $proceed($token);
	}
}
