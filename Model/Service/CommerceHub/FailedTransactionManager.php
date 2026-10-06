<?php

namespace Fiserv\Payments\Model\Service\CommerceHub;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Fiserv\Payments\Api\FailedTransaction\FailedTransactionRepositoryInterface;
use Fiserv\Payments\Model\FailedTransactionFactory;
use Fiserv\Payments\Model\ResourceModel\FailedTransaction;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Validator\CommerceHub\TransactionResponseValidator;
use Fiserv\Payments\Model\OpenRefund;

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

	public function createFailedTransaction($merchantOrderId, $txnResponse, $paths, $paymentAction, $surchargeAmount = null, array $additionalData = [])
	{
		$failedTxn = $this->failedTxnFactory->create();
		$failedTxn = $this->populateFailedTxn($failedTxn, $txnResponse, $paths, $paymentAction);
		$failedTxn->setOrderIncrementId($merchantOrderId);
		if ($surchargeAmount !== null && $surchargeAmount > 0) {
			$failedTxn->setSurchargeEstimate($surchargeAmount);
		}
		$failedTxn->addData($additionalData);
		$this->failedTxnRepo->save($failedTxn);
		
		return $failedTxn;
	}

	public function createOpenRefundFailedTransaction(OpenRefund $refund, array $response, string $message)
	{
		$identifier = (string)$refund->getData(OpenRefund::KEY_OPEN_REFUND_ID);
		$gateway = $response['gatewayResponse'] ?? [];
		$processor = $response['paymentReceipt']['processorResponseDetails'] ?? [];
		$error = $response['error']['message'] ?? $response['error'][0]['message'] ?? '';
		$approval = $gateway['approvalStatus'] ?? 'DECLINED';
		if ($response === []) {
			$approval = $message;
		}
		$response['countryCode'] = $response['cardDetails']['countryCode'] ?? $response['countryCode'] ?? null;
		// Use the same envelope and mapping as checkout transaction responses.
		$wrappedResponse = [
			HttpClient::RESPONSE_KEY => $response,
			HttpClient::STATUS_CODE_KEY => $response['_http_status'] ?? 0
		];
		return $this->createFailedTransaction($identifier, $wrappedResponse, TransactionResponseValidator::RESPONSE_PATHS, 'OPEN_REFUND', null, [
			'date_time' => gmdate('Y-m-d H:i:s'),
			'remote_ip' => 'Admin',
			'transaction_id' => ($gateway['transactionProcessingDetails']['transactionId'] ?? '') ?: 'OPEN_REFUND_' . $identifier,
			'transaction_state' => mb_substr((string)($gateway['transactionState'] ?? 'ERROR'), 0, 50),
			'approval_status' => mb_substr((string)($error ?: $approval), 0, 50),
			'total_amount' => (float)$refund->getData(OpenRefund::KEY_AMOUNT),
			'currency' => (string)$refund->getData(OpenRefund::KEY_CURRENCY_CODE),
			'responseMessage' => mb_substr($message, 0, 255),
			'host_response_message' => mb_substr((string)($processor['hostResponseMessage'] ?? ''), 0, 32),
			'network_response_code' => mb_substr((string)($response['error']['code'] ?? $response['error'][0]['code'] ?? $response['networkDetails']['networkResponseCode'] ?? ''), 0, 32)
		]);
	}

	public function populateFailedTxn($failedTxn, $txnResponse, $paths, $paymentAction)
	{
		$transactionState = SubjectReader::getValueSafely($txnResponse, 'transactionState', $paths['transactionState']);
		$transactionId = SubjectReader::getValueSafely($txnResponse, 'transactionId', $paths['transactionId']);
		$apiTraceId = SubjectReader::getValueSafely($txnResponse, 'apiTraceId', $paths['apiTraceId']);
		$approvalStatus = SubjectReader::getValueSafely($txnResponse, 'approvalStatus', $paths['approvalStatus']);
		$totalAmount = SubjectReader::getValueSafely($txnResponse, 'total', $paths['approvedAmount']);
		if (!isset($totalAmount) && isset($paths['amount']))
		{
			$totalAmount = SubjectReader::getValueSafely($txnResponse, 'amount', $paths['amount']);
		}
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
		$retrievalReferenceNumber = SubjectReader::getValueSafely($txnResponse, 'retrievalReferenceNumber', $paths['retrievalReferenceNumber']);
		$countryCode = SubjectReader::getValueSafely($txnResponse, 'countryCode', $paths['countryCode']);
		$responseCode = SubjectReader::getValueSafely($txnResponse, 'responseCode', $paths['responseCode']);
		$responseMessage = SubjectReader::getValueSafely($txnResponse, 'responseMessage', $paths['responseMessage']);
		$merchantAdviceCode = SubjectReader::getValueSafely($txnResponse, 'merchantAdviceCode', $paths['merchantAdviceCode']);
		$errorCode = SubjectReader::getValueSafely($txnResponse, 'code', $paths['errorCode']);
		$errorMessage = SubjectReader::getValueSafely($txnResponse, 'message', $paths['errorMessage']);
		$securityCodeMatch = SubjectReader::getValueSafely($txnResponse, 'securityCodeMatch', $paths['securityCodeMatch']);
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
		$failedTxn->setRetrievalReferenceNumber($retrievalReferenceNumber);
		$failedTxn->setCountry($countryCode);
		$failedTxn->setResponseCode($responseCode);
		$failedTxn->setResponseMessage($responseMessage);
		$failedTxn->setMerchantAdviceCode($merchantAdviceCode);
		$failedTxn->setSecurityCodeMatch($securityCodeMatch);
		$failedTxn->setPaymentAction($paymentAction);

		return $failedTxn;
	}
}
