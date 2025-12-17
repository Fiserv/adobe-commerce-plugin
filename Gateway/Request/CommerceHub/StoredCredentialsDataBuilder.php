<?php
declare(strict_types=1);
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Lib\CommerceHub\Model\StoredCredentials;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;

class StoredCredentialsDataBuilder implements BuilderInterface
{
	public const STORED_CREDENTIALS_KEY = 'storedCredentials';

	public function __construct(
		private SubjectReader $subjectReader,
		private MultiLevelLogger $logger
	) {}

	public function build(array $buildSubject): array
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
		$orderIncrementId = $orderDO ? (string)$orderDO->getOrderIncrementId() : '';

		$isSubscription = (bool)($payment->getAdditionalInformation('is_subscription') ?? false);
		if (!$isSubscription) {
			return [];
		}

		if (!class_exists(StoredCredentials::class)) {
			$this->logger->logDebug(1, 'StoredCredentials class not found');
			return [];
		}

		try {
			$storedCredentials = new StoredCredentials();
		} catch (\Throwable $e) {
			$this->logger->logDebug(1, 'Error instantiating StoredCredentials: ' . $e->getMessage());
			return [];
		}

		// Deterministic rule based on increment id:
		// - ROOT (no suffix) => FIRST
		// - ROOT-### => SUBSEQUENT
		$isRenewal = ($orderIncrementId !== '') && (bool)preg_match('/-\d+$/', $orderIncrementId);

		if ($isRenewal) {
			$storedCredentials->setSequence('SUBSEQUENT');
			$storedCredentials->setInitiator('MERCHANT');
			$storedCredentials->setScheduled(true);

			$schemeRefTxnId = trim((string)($payment->getAdditionalInformation('scheme_reference_transaction_id') ?? ''));
			if ($schemeRefTxnId !== '') {
				$storedCredentials->setSchemeReferenceTransactionId($schemeRefTxnId);
			}
		} else {
			$storedCredentials->setSequence('FIRST');
			$storedCredentials->setInitiator('CARD_HOLDER');
			$storedCredentials->setScheduled(false);
		}

		$this->logger->logDebug(3, "Stored Credentials Data Builder:\n" . $storedCredentials->__toString(), "Order ID: $orderIncrementId");

		return [self::STORED_CREDENTIALS_KEY => $storedCredentials];
	}
}
