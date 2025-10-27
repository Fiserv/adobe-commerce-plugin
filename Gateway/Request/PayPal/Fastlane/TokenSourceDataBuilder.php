<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal\Fastlane;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Observer\CommerceHub\DataAssignObserver;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentToken;
use Fiserv\Payments\Lib\CommerceHub\Model\Card;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Token Payment Data Builder
 */
class TokenSourceDataBuilder implements BuilderInterface
{
	use Formatter;

	const TOKEN_SOURCE_KEY = "tokenSource";
	const CARD_ID_KEY = "fastlane_card_id";

	const TOKEN_SOURCE_TYPE = "PaymentToken";
	const PAYMENT_TOKEN_SOURCE = "FASTLANE";
	const WALLET_TYPE = "FASTLANE";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @param MultiLevelLogger $logger
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
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
		
		$tokenData = $payment->getAdditionalInformation(self::CARD_ID_KEY);

		$source = new PaymentToken();
		$source->setSourceType(self::TOKEN_SOURCE_TYPE);
		$source->setWalletType(self::WALLET_TYPE);
		$source->setTokenData($tokenData);
		$source->setTokenSource(self::PAYMENT_TOKEN_SOURCE);
		$source->setDeclineDuplicates(false);

		$this->logger->logDebug(3, "Fastlane Token Source Data Builder:\n" . $source->__toString(), "Order ID: $orderIncrementId");

		return [ self::TOKEN_SOURCE_KEY => $source ];
	}
}
