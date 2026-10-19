<?php


namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\StoredCredentials;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Builds the CommerceHub `storedCredentials` object for  multi-use token flows only.
 */
class StoredCredentialsDataBuilder implements BuilderInterface
{
	const STORED_CREDENTIALS_KEY = 'storedCredentials';

	const TOKEN_USE_TYPE_MULTI = 'MULTI_USE';

	const SEQUENCE_FIRST = 'FIRST';
	const SEQUENCE_SUBSEQUENT = 'SUBSEQUENT';
	const INITIATOR_CARD_HOLDER = 'CARD_HOLDER';
	const INITIATOR_MERCHANT = 'MERCHANT';

	private $subjectReader;
	private $logger;
	private $appState;

	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger,
		State $appState
	) {
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
		$this->appState = $appState;
	}

	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
		$orderIncrementId = $orderDO->getOrderIncrementId();

		$extensionAttributes = $payment->getExtensionAttributes();
		$vaultPaymentToken = $extensionAttributes ? $extensionAttributes->getVaultPaymentToken() : null;

		// Case 1: reuse of an existing vaulted token (vault_authorize / vault_sale).
		if ($vaultPaymentToken instanceof PaymentTokenInterface) {
			$details = $this->getTokenDetailsArray($vaultPaymentToken);
			$tokenUseType = $details['tokenUseType'] ?? null;
			$storedAccountReference = $details['storedAccountReference'] ?? null;

			if ($tokenUseType !== self::TOKEN_USE_TYPE_MULTI) {
				// Single-use (or unknown/legacy) token: no storedCredentials.
				return [];
			}

			$storedCredentials = new StoredCredentials();
			// Saved-card payments created in Adobe Admin are merchant-initiated.
			$storedCredentials->setInitiator(
				$this->isAdminArea() ? self::INITIATOR_MERCHANT : self::INITIATOR_CARD_HOLDER
			);
			$storedCredentials->setSequence(self::SEQUENCE_SUBSEQUENT);
			$storedCredentials->setScheduled(false);
			if (!empty($storedAccountReference)) {
				$storedCredentials->setSchemeReferenceTransactionId($storedAccountReference);
			}

			$this->logger->logDebug(
				3,
				"Stored Credentials Data Builder (reuse):\n" . $storedCredentials->__toString(),
				"Order ID: $orderIncrementId"
			);

			return [ self::STORED_CREDENTIALS_KEY => $storedCredentials ];
		}

		// Case 2: first-time tokenization. Magento persists this standard vault flag
		// before the gateway request is built.
		$isSavingToken = !empty($payment->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE));
		if ($isSavingToken) {
			$storedCredentials = new StoredCredentials();
			$storedCredentials->setInitiator(self::INITIATOR_CARD_HOLDER);
			$storedCredentials->setSequence(self::SEQUENCE_FIRST);
			$storedCredentials->setScheduled(false);

			$this->logger->logDebug(
				3,
				"Stored Credentials Data Builder (tokenization):\n" . $storedCredentials->__toString(),
				"Order ID: $orderIncrementId"
			);

			return [ self::STORED_CREDENTIALS_KEY => $storedCredentials ];
		}

		return [];
	}

	private function isAdminArea(): bool
	{
		try {
			return $this->appState->getAreaCode() === Area::AREA_ADMINHTML;
		} catch (LocalizedException $exception) {
			return false;
		}
	}

	private function getTokenDetailsArray(PaymentTokenInterface $vaultPaymentToken): array
	{
		$details = $vaultPaymentToken->getTokenDetails();
		if (empty($details) || !is_string($details)) {
			return [];
		}

		$decoded = json_decode($details, true);
		return is_array($decoded) ? $decoded : [];
	}
}

