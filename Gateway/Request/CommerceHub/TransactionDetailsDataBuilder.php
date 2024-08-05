<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;

/**
 * Payment Data Builder
 */
abstract class TransactionDetailsDataBuilder implements BuilderInterface
{
	const TXN_DETAILS_KEY = "transactionDetails";
	const KEY_CREATE_TOKEN = 'T';
	const CAPTURE = true;
	const AUTHORIZE = false;

	/**
	 * @var SubjectReader
	 */
	protected $subjectReader;

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

		$txnDetails = new TransactionDetails();

		$data = $payment->getAdditionalInformation();
		
		$createToken = !empty($data[VaultConfigProvider::IS_ACTIVE_CODE]);
		$txnDetails->setCreateToken($createToken);
		if ($createToken == true) 
		{ 
        	$payment->setStoreVault(self::KEY_CREATE_TOKEN);
		}

		$captureFlag = $this->getCaptureFlag();
		
		$txnDetails->setCaptureFlag($captureFlag);
		$txnDetails->setMerchantOrderId($orderDO->getOrderIncrementId());
		$txnDetails->setAccountVerification(false);

		return [ self::TXN_DETAILS_KEY => $txnDetails ];
	}

	/**
	 * Get Capture Flag
	 * @return bool
	 */
	abstract protected function getCaptureFlag();
}
