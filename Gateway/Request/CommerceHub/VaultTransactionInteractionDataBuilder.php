<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

class VaultTransactionInteractionDataBuilder implements BuilderInterface
{
    const TXN_INTERACTION_KEY = "transactionInteraction";
    const ORIGIN = "ECOM";
    const POS_CONDITION_CODE = "CARD_NOT_PRESENT_ECOM";

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
        $orderDO = $paymentDO->getOrder();
        $orderIncrementId = $orderDO->getOrderIncrementId();

        $txnInteraction = new TransactionInteraction();
        $txnInteraction->setOrigin(self::ORIGIN);
        $txnInteraction->setPosConditionCode(self::POS_CONDITION_CODE);

        $this->logger->logDebug(3, "Transaction Interaction Builder:\n" . $txnInteraction->__toString(), "Order ID: $orderIncrementId");

        return [self::TXN_INTERACTION_KEY => $txnInteraction];
    }
}
