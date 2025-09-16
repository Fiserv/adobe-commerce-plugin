<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal;

use Fiserv\Payments\Gateway\Subject\PayPal\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Gateway\Config\PayPal\Config;
use Fiserv\Payments\Model\Source\PayPal\TokenizationStrategy;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Payment Data Builder
 */
abstract class PayPalTransactionDetailsDataBuilder implements BuilderInterface
{
	const TXN_DETAILS_KEY = "transactionDetails";
	const KEY_CREATE_TOKEN = 'T';
	const CAPTURE = true;
	const AUTHORIZE = false;

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var SubjectReader
	 */
	protected $subjectReader;

	private $paypalConfig;

	/**
	 * @param MultiLevelLogger $logger
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(
		SubjectReader $subjectReader,
		Config $paypalConfig,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->paypalConfig = $paypalConfig;
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
		
		$tokenStrat = $this->paypalConfig->getTokenStrategy();
		$createToken = !empty($data[VaultConfigProvider::IS_ACTIVE_CODE]) || $tokenStrat === TokenizationStrategy::ALWAYS;
		$txnDetails->setCreateToken($createToken);
		if ($createToken == true) 
		{ 
        	$payment->setStoreVault(self::KEY_CREATE_TOKEN);
		}

		$captureFlag = $this->getCaptureFlag();

		$txnDetails->setCaptureFlag($captureFlag);
		$txnDetails->setOperationType($captureFlag ? 'SALE' : 'AUTHORIZE');
		$txnDetails->setMerchantOrderId($orderIncrementId);
		$txnDetails->setMerchantTransactionId(uniqid());
		$txnDetails->setAccountVerification(false);

		$this->logger->logDebug(3, "Transaction Details Data Builder:\n" . $txnDetails->__toString(), "Order ID: $orderIncrementId");

		return [ self::TXN_DETAILS_KEY => $txnDetails ];
	}

	/**
	 * Get Capture Flag
	 * @return bool
	 */
	abstract protected function getCaptureFlag();
}
