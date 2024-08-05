<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;

/**
 * Payment Data Builder
 */
class TransactionInteractionDataBuilder implements BuilderInterface
{
	const TXN_INTERACTION_KEY = "transactionInteraction";
	const ORIGIN = "ECOM";
	const ECI_INDICATOR = "CHANNEL_ENCRYPTED";
	const POS_CONDITION_CODE = "CARD_NOT_PRESENT_ECOM";


	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(SubjectReader $subjectReader)
	{
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();

		$txnInteraction = new TransactionInteraction();
		$txnInteraction->setOrigin(self::ORIGIN);
		$txnInteraction->setEciIndicator(self::ECI_INDICATOR);
		$txnInteraction->setPosConditionCode(self::POS_CONDITION_CODE);

		return [ self::TXN_INTERACTION_KEY => $txnInteraction ];
	}
}
