<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal\Fastlane;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config; 
use Fiserv\Payments\Model\Source\CommerceHub\TokenizationStrategy;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Observer\PayPal\Fastlane\DataAssignObserver;

/**
 * Payment Data Builder
 */
abstract class TransactionDetailsDataBuilder implements BuilderInterface
{
	const TXN_DETAILS_KEY = "transactionDetails";
	const KEY_CREATE_TOKEN = 'T';
	const CAPTURE = true;
	const AUTHORIZE = false;
	const FASTLANE_TOKEN_PROVIDER = "FASTLANE";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var SubjectReader
	 */
	protected $subjectReader;

	private $chConfig;

	/**
	 * @param MultiLevelLogger $logger
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(
		SubjectReader $subjectReader,
		Config $chConfig,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->chConfig = $chConfig;
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

		$txnDetails = new TransactionDetails();

		$data = $payment->getAdditionalInformation();
		
		$tokenStrat = $this->chConfig->getTokenStrategy();
		$createToken = !empty($data[VaultConfigProvider::IS_ACTIVE_CODE]) || $tokenStrat === TokenizationStrategy::ALWAYS;
		$txnDetails->setCreateToken($createToken);
		if ($createToken == true) 
		{ 
        	$payment->setStoreVault(self::KEY_CREATE_TOKEN);
		}

		$captureFlag = $this->getCaptureFlag();
		
		$txnDetails->setCaptureFlag($captureFlag);
		$txnDetails->setMerchantOrderId($orderDO->getOrderIncrementId());
		$txnDetails->setMerchantTransactionId(uniqid());
		$txnDetails->setProviderSessionId(isset($data[DataAssignObserver::FASTLANE_SESSION_ID]) ? $data[DataAssignObserver::FASTLANE_SESSION_ID] : "");
		$txnDetails->setTokenProvider(self::FASTLANE_TOKEN_PROVIDER);

		$this->logger->logDebug(3, "Transaction Details Data Builder:\n" . $txnDetails->__toString(), "Order ID: $orderIncrementId");

		return [ self::TXN_DETAILS_KEY => $txnDetails ];
	}

	/**
	 * Get Capture Flag
	 * @return bool
	 */
	abstract protected function getCaptureFlag();
}
