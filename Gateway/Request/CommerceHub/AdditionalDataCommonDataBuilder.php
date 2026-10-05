<?php
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\AdditionalDataCommon;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Builds the CommerceHub `additionalDataCommon` object for stored-credential subsequent transactions.
 */
class AdditionalDataCommonDataBuilder implements BuilderInterface
{
	const ADDITIONAL_DATA_COMMON_KEY = 'additionalDataCommon';

	const BILL_PAYMENT_TYPE_SINGLE = 'SINGLE';

	private $subjectReader;
	private $logger;

	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
	}

	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
		$orderIncrementId = $orderDO->getOrderIncrementId();

		$extensionAttributes = $payment->getExtensionAttributes();
		$vaultPaymentToken = $extensionAttributes ? $extensionAttributes->getVaultPaymentToken() : null;

		// Only subsequent transactions reusing a MULTI_USE token require billPaymentType.
		// SINGLE_USE token reuse does not send storedCredentials
		if (!$vaultPaymentToken instanceof PaymentTokenInterface || !$this->isMultiUseToken($vaultPaymentToken)) {
			return [];
		}

		$additionalDataCommon = new AdditionalDataCommon();
		$additionalDataCommon->setBillPaymentType(self::BILL_PAYMENT_TYPE_SINGLE);

		$this->logger->logDebug(
			3,
			"Additional Data Common Builder:\n" . $additionalDataCommon->__toString(),
			"Order ID: $orderIncrementId"
		);

		return [ self::ADDITIONAL_DATA_COMMON_KEY => $additionalDataCommon ];
	}

	private function isMultiUseToken(PaymentTokenInterface $vaultPaymentToken): bool
	{
		$details = $vaultPaymentToken->getTokenDetails();
		if (empty($details) || !is_string($details)) {
			return false;
		}

		$decoded = json_decode($details, true);
		$tokenUseType = is_array($decoded) ? ($decoded['tokenUseType'] ?? null) : null;

		return $tokenUseType === StoredCredentialsDataBuilder::TOKEN_USE_TYPE_MULTI;
	}
}

