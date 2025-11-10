<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Gateway\Config\PayPal\Config;
use Fiserv\Payments\Gateway\Config\Venmo\Config as VenmoConfig;
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
	const REFUND = false;
	const CANCEL = false;

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var SubjectReader
	 */
	protected $subjectReader;

	private $paypalConfig;
	private $venmoConfig;

	/**
	 * @param MultiLevelLogger $logger
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(
		SubjectReader $subjectReader,
		Config $paypalConfig,
		VenmoConfig $venmoConfig,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->paypalConfig = $paypalConfig;
		$this->venmoConfig = $venmoConfig;
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

		$data = $payment->getAdditionalInformation();
		$captureFlag = $this->getCaptureFlag();
		$refundFlag = isset($data['is_refund']) ? (bool)$data['is_refund'] : false;
		$voidFlag = isset($data['is_void']) ? (bool)$data['is_void'] : false;

		if ($refundFlag) {
			$operationType = 'REFUND';
		} elseif ($voidFlag) {
			$operationType = 'CANCEL';
		} elseif ($captureFlag === true) {
			$operationType = 'CAPTURE';
		} else {
			$operationType = 'AUTHORIZE';
		}

		$this->logger->logDebug(3, "Transaction Details Data Builder:\n" . $operationType, "Order ID: $orderIncrementId");

		// Always return as ['operationType' => ...]
		return [ self::TXN_DETAILS_KEY => ['operationType' => $operationType] ];
	}

	/**
	 * Get Capture Flag
	 * @return bool
	 */
	abstract protected function getCaptureFlag();
}
