<?php

namespace Fiserv\Payments\Model\Service\CommerceHub;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Fiserv\Payments\Api\FailedTransaction\FailedTransactionRepositoryInterface;
use Fiserv\Payments\Model\FailedTransactionFactory;
use Fiserv\Payments\Model\ResourceModel\FailedTransaction;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;

class FailedTransactionManager
{
	private $failedTxnRepo;

	private $failedTxnFactory;

	public function __construct(
		FailedTransactionRepositoryInterface $failedTxnRepo,
		FailedTransactionFactory $failedTxnFactory
	) {
		$this->failedTxnRepo = $failedTxnRepo;
		$this->failedTxnFactory = $failedTxnFactory;
	}

	public function createFailedTransaction($merchantOrderId, $txnResponse, $paths)
	{
		$failedTxn = $this->failedTxnFactory->create();
		$failedTxn = $this->populateFailedTxn($failedTxn, $txnResponse, $paths);
		$failedTxn->setOrderIncrementId($merchantOrderId);
		$this->failedTxnRepo->save($failedTxn);

		return $failedTxn;
	}

	public function populateFailedTxn($failedTxn, $txnResponse, $paths)
	{
		$transactionState = SubjectReader::getValueSafely($txnResponse, 'transactionState', $paths['transactionState']);
		$transactionId = SubjectReader::getValueSafely($txnResponse, 'transactionId', $paths['transactionId']);
		$apiTraceId = SubjectReader::getValueSafely($txnResponse, 'apiTraceId', $paths['apiTraceId']);
		$approvalStatus = SubjectReader::getValueSafely($txnResponse, 'approvalStatus', $paths['approvalStatus']);
		$totalAmount = SubjectReader::getValueSafely($txnResponse, 'total', $paths['approvedAmount']);
		$remoteIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
		$currency = SubjectReader::getValueSafely($txnResponse, 'currency', $paths['currency']);
		$bankAssociationDetails = isset($paths['bankAssociationDetails']) ? SubjectReader::getValueSafely($txnResponse, 'associationResponseCode', $paths['bankAssociationDetails']) : null;
		$processor = SubjectReader::getValueSafely($txnResponse, 'processor', $paths['processor']);
		$host = SubjectReader::getValueSafely($txnResponse, 'host', $paths['host']);
		$merchantId = SubjectReader::getValueSafely($txnResponse, 'merchantId', $paths['merchantId']);
		$expirationMonth = SubjectReader::getValueSafely($txnResponse, 'expirationMonth', $paths['expirationMonth']);
		$expirationYear = SubjectReader::getValueSafely($txnResponse, 'expirationYear', $paths['expirationYear']);
		$last4 = SubjectReader::getValueSafely($txnResponse, 'last4', $paths['last4']);
		$scheme = SubjectReader::getValueSafely($txnResponse, 'scheme', $paths['scheme']);
		$networkResponseCode = SubjectReader::getValueSafely($txnResponse, 'networkResponseCode', $paths['networkResponseCode']);
		$bin = SubjectReader::getValueSafely($txnResponse, 'bin', $paths['bin']);
		$hostResponseMessage = SubjectReader::getValueSafely($txnResponse, 'hostResponseMessage', $paths['hostResponseMessage']);
		$countryCode = SubjectReader::getValueSafely($txnResponse, 'countryCode', $paths['countryCode']);

		$errorCode = SubjectReader::getValueSafely($txnResponse, 'code', $paths['errorCode']);
		$errorMessage = SubjectReader::getValueSafely($txnResponse, 'message', $paths['errorMessage']);
		if (isset($errorCode))
		{
			$networkResponseCode = $errorCode;
		}		
		if (isset($errorMessage))
		{
			$approvalStatus = $errorMessage;
		}

		$failedTxn->setTransactionState($transactionState);
		$failedTxn->setApprovalStatus($approvalStatus);
		$failedTxn->setTotalAmount($totalAmount);
		$failedTxn->setRemoteIp($remoteIp); // Set remote IP
		$failedTxn->setApiTraceId($apiTraceId); // Set API Trace ID
		$failedTxn->setProcessor($processor);
		$failedTxn->setHost($host);
		$failedTxn->setMerchantId($merchantId);
		$failedTxn->setExpirationMonth($expirationMonth);
		$failedTxn->setExpirationYear($expirationYear);
		$failedTxn->setLast4($last4);
		$failedTxn->setScheme($scheme);
		$failedTxn->setNetworkResponseCode($networkResponseCode);
		$failedTxn->setBin($bin);
		$failedTxn->setTransactionId($transactionId);
		$failedTxn->setCurrency($currency); // Set currency
		$failedTxn->setBankAssociationDetails($bankAssociationDetails); // Set bank association details
		$failedTxn->setHostResponseMessage($hostResponseMessage);
		$failedTxn->setCountry($countryCode);

		return $failedTxn;
	}
}
