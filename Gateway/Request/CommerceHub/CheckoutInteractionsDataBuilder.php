<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\CheckoutInteractions;
use Fiserv\Payments\Gateway\Config\PayByLink\Config as PblConfig;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Payment Data Builder
 */
class CheckoutInteractionsDataBuilder implements BuilderInterface
{
	const CHECKOUT_INTERACTION_KEY = "checkoutInteractions";
	const SUCCESS_URL = 'successUrl';
	const FAILURE_URL = 'failureUrl';
	const CANCEL_URL = 'cancelUrl';


	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @var PblConfig
	 */
	private $pblConfig;

	/**
	 * @param MultiLevelLogger $logger
	 * @param SubjectReader $subjectReader
	 * @param PblConfig $pblConfig
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger,
		PblConfig $pblConfig
	) {
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
		$this->pblConfig = $pblConfig;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		    
		$expirationMinutes = (int) $this->pblConfig->getExpiryTime();
		$paymentPageId = (int) $this->pblConfig->getPaymentPageId();
		$returnUrls = [];
		$returnUrls[ self::SUCCESS_URL ] = "https://merchant.com/success?id=1234";
		$returnUrls[ self::FAILURE_URL ] = "https://merchant.com/failure?id=1234";
		$returnUrls[ self::CANCEL_URL ] = "https://merchant.com/cancel?id=1234";

		$checkoutInteraction = new CheckoutInteractions();
		$checkoutInteraction->setReturnUrls($returnUrls);
		$checkoutInteraction->setPaymentPageId($paymentPageId);
		$checkoutInteraction->setExpirationMinutes($expirationMinutes);

		$this->logger->logDebug(3, "Checkout Interaction Builder:\n" . $checkoutInteraction->__toString(), "Order ID: $orderIncrementId");

		return [ self::CHECKOUT_INTERACTION_KEY => $checkoutInteraction ];
	}
}
