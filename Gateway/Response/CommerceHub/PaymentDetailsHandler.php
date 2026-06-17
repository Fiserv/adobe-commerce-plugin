<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentDetailsHandler implements HandlerInterface
{
	const API_TRACE_ID = "apiTraceId";
	const KEY_AMOUNT = "amount";
	const TXN_TIMESTAMP = "txnTimestamp";

	/** @var SubjectReader */
	private $subjectReader;

	public function __construct(SubjectReader $subjectReader)
	{
		$this->subjectReader = $subjectReader;
	}

	public function handle(array $handlingSubject, array $response)
	{
		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();

		$chResponse = $this->subjectReader->readChResponse($response)[HttpClient::RESPONSE_KEY];
		$tnxDetails = $chResponse["gatewayResponse"]["transactionProcessingDetails"];

		$payment->setLastTransId($tnxDetails["transactionId"]);
		$payment->setTransactionId($tnxDetails["transactionId"]);
		$payment->setShouldCloseParentTransaction(false);
		$payment->setIsTransactionClosed(false);
		$payment->setTransactionAdditionalInfo(self::API_TRACE_ID, $tnxDetails["apiTraceId"]);

        $amount = $chResponse["paymentReceipt"]["approvedAmount"]["total"] ?? null;

		if ($amount !== null) {
			$payment->setTransactionAdditionalInfo(self::KEY_AMOUNT, (float)$amount);
		}
	}
}
