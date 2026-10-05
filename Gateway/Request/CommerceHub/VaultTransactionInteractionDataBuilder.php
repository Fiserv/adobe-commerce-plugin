<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

class VaultTransactionInteractionDataBuilder implements BuilderInterface
{
    const TXN_INTERACTION_KEY = "transactionInteraction";
    const ORIGIN = "ECOM";
    const POS_CONDITION_CODE = "CARD_NOT_PRESENT_ECOM";
    const POS_ENTRY_MODE_CREDENTIAL_ON_FILE = "CREDENTIAL_ON_FILE";

    private $subjectReader;
    private $logger;

    public function __construct(
        SubjectReader $subjectReader,
        MultiLevelLogger $logger
    ) {
        $this->subjectReader = $subjectReader;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $orderDO = $paymentDO->getOrder();
        $orderIncrementId = $orderDO->getOrderIncrementId();

        $txnInteraction = new TransactionInteraction();
        $txnInteraction->setOrigin(self::ORIGIN);
        $txnInteraction->setPosConditionCode(self::POS_CONDITION_CODE);

        if ($this->isMultiUseStoredCredential($payment)) {
            $txnInteraction->setPosEntryMode(self::POS_ENTRY_MODE_CREDENTIAL_ON_FILE);
        }

        $this->logger->logDebug(3, "Transaction Interaction Builder:\n" . $txnInteraction->__toString(), "Order ID: $orderIncrementId");

        return [self::TXN_INTERACTION_KEY => $txnInteraction];
    }


    // Determine whether the vault token being reused on this payment is a MULTI_USE (stored-credential) token
    private function isMultiUseStoredCredential(InfoInterface $payment): bool
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        $vaultPaymentToken = $extensionAttributes ? $extensionAttributes->getVaultPaymentToken() : null;

        if (!$vaultPaymentToken instanceof PaymentTokenInterface) {
            return false;
        }

        $details = $vaultPaymentToken->getTokenDetails();
        if (empty($details) || !is_string($details)) {
            return false;
        }

        $decoded = json_decode($details, true);
        $tokenUseType = is_array($decoded) ? ($decoded['tokenUseType'] ?? null) : null;

        return $tokenUseType === StoredCredentialsDataBuilder::TOKEN_USE_TYPE_MULTI;
    }
}
