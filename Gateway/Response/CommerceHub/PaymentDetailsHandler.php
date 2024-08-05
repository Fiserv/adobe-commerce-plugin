<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Class PaymentDetailsHandler
 */
class PaymentDetailsHandler implements HandlerInterface
{
	const API_TRACE_ID = "apiTraceId";
	const ORDER_ID = "orderId";
	const TXN_TIMESTAMP = "txnTimestamp";

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * Constructor
	 *
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(
		SubjectReader $subjectReader
	) {
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $handlingSubject, array $response)
	{
		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();

		$chResponse = $this->subjectReader->readChResponse($response)[\Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient::RESPONSE_KEY];

		$tnxDetails = $chResponse["gatewayResponse"]["transactionProcessingDetails"];

		$transId = $tnxDetails["transactionId"];
		$payment->setLastTransId($transId);
		$payment->setTransactionId($transId);

		$payment->setShouldCloseParentTransaction(false);
		$payment->setIsTransactionClosed(false);

		$payment->setTransactionAdditionalInfo(
			self::API_TRACE_ID,
			$tnxDetails["apiTraceId"]
		);

		$payment->setTransactionAdditionalInfo(
			self::API_TRACE_ID,
			$tnxDetails["orderId"]
		);

		$payment->setTransactionAdditionalInfo(
			self::API_TRACE_ID,
			$tnxDetails["orderId"]
		);
	}
}
