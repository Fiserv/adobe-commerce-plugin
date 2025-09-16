<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal\Composite;

use Fiserv\Payments\Gateway\Request\PayPal\Composite\PayPalCompositeBase;
use Fiserv\Payments\Gateway\Request\PayPal\PayPalTransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\PayPal\MerchantDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\PayPal\ReferenceTransactionDataBuilder;
use Fiserv\Payments\Gateway\Subject\PayPal\SubjectReader;
use Fiserv\Payments\Observer\PayPal\DataAssignObserver;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\ObjectManager\TMapFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Gateway\Config\PayPal\Config;

/**
 * Class SessionAuthComposite
 */
class SessionAuthComposite extends PayPalCompositeBase
{
	const ENDPOINT = "checkouts/v1/orders";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	private $paypalConfig;

	private $subjectReader;

	/**
	 * @param MultiLevelLogger $logger
	 * @param TMapFactory $tmapFactory
	 * @param Config $paypalConfig
	 * @param SubjectReader $subjectReader
	 * @param array $builders
	 */
	public function __construct(
			MultiLevelLogger $logger,
			TMapFactory $tmapFactory,
			Config $paypalConfig,
			SubjectReader $subjectReader,
			array $builders = []
	) {
		parent::__construct($tmapFactory, $builders);
		$this->logger = $logger;
		$this->paypalConfig = $paypalConfig;
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$result = parent::build($buildSubject);

		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
		$orderIncrementId = $orderDO ? $orderDO->getOrderIncrementId() : $payment->getQuote()->getReservedOrderId();

		$this->logger->logInfo(1, "Initiating PayPal Auth Transaction", "Order ID: " . $orderIncrementId);

		// Get PayPal order ID as reference order ID
		$sessionId = $payment->getAdditionalInformation(DataAssignObserver::ORDER_ID);

		// Determine operation type based on capture flag
		$txnDetails = $result[PayPalTransactionDetailsDataBuilder::TXN_DETAILS_KEY];
		$captureFlag = $txnDetails->getCaptureFlag();
		$operationType = $captureFlag ? "CAPTURE" : "AUTHORIZE";

		// Build transaction details with operation type
		$transactionDetails = [
			"operationType" => $operationType
		];

		// Build reference transaction details
		$referenceTransactionDetails = [
			"referenceOrderId" => $sessionId
		];

		// Get merchant details
		$merchantDetails = $result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY];

		// Build the request payload
		$requestPayload = [
			"transactionDetails" => $transactionDetails,
			"referenceTransactionDetails" => $referenceTransactionDetails,
			"merchantDetails" => [
				"merchantId" => $merchantDetails->getMerchantId(),
				"terminalId" => $merchantDetails->getTerminalId()
			]
		];

		return [
			self::REQUEST_KEY => $requestPayload,
			self::ENDPOINT_KEY => self::ENDPOINT
		];
	}
}
