<?php
/**
 * EBT Online Transaction Interaction Data Builder
 *
 * Builds the transactionInteraction block required for EBT Online charges.
 * Adds terminalTimestamp (mandatory for EBT) plus the same ECI / POS
 * indicators used by the standard CommerceHub ECOM card flow.
 */
namespace Fiserv\Payments\Gateway\Request\EbtOnline;

use Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction;
use Magento\Payment\Gateway\Request\BuilderInterface;

class EbtOnlineTransactionInteractionDataBuilder implements BuilderInterface
{
    const TXN_INTERACTION_KEY  = 'transactionInteraction';
    const ORIGIN_ECOM = 'ECOM';
    const ECI_INDICATOR = 'CHANNEL_ENCRYPTED';
    const POS_ENTRY_MODE = 'MANUAL';
    const POS_CONDITION_CODE = 'CARD_NOT_PRESENT_ECOM';

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $txnInteraction = new TransactionInteraction();
        $txnInteraction->setOrigin(self::ORIGIN_ECOM);
        $txnInteraction->setEciIndicator(self::ECI_INDICATOR);
        $txnInteraction->setPosEntryMode(self::POS_ENTRY_MODE);
        $txnInteraction->setPosConditionCode(self::POS_CONDITION_CODE);
        $txnInteraction->setTerminalTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));

        return [
            self::TXN_INTERACTION_KEY => $txnInteraction,
        ];
    }
}
