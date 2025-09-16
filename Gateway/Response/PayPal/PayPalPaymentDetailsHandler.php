<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\PayPal;

use Fiserv\Payments\Gateway\Subject\PayPal\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Class PaymentDetailsHandler
 */
class PaymentDetailsHandler implements HandlerInterface
{
	const API_TRACE_ID = "apiTraceId";
	const KEY_AMOUNT = "amount";
	const KEY_ORDER_ID = "orderId";
	const TXN_TIMESTAMP = "txnTimestamp";

	private $amountPath = array( "total" => "paymentReceipt", "approvedAmount" );

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

		$paypalResponse = $this->subjectReader->readPayPalResponse($response)[\Fiserv\Payments\Gateway\Http\PayPal\Client\HttpClient::RESPONSE_KEY];

		$tnxDetails = $paypalResponse["gatewayResponse"]["transactionProcessingDetails"];

		$transId = $tnxDetails["transactionId"];
		$payment->setLastTransId($transId);
		$payment->setTransactionId($transId);

		$payment->setShouldCloseParentTransaction(false);

		// Check transaction state to determine if transaction should be closed
		$transactionState = $paypalResponse["gatewayResponse"]["transactionState"];
		if ($transactionState === 'CAPTURED') {
			$payment->setIsTransactionClosed(true);
		} else {
			$payment->setIsTransactionClosed(false);
		}

		$payment->setTransactionAdditionalInfo(
			self::API_TRACE_ID,
			$tnxDetails["apiTraceId"]
		);

		$payment->setTransactionAdditionalInfo(
			self::KEY_ORDER_ID,
			$tnxDetails["orderId"]
		);

		$payment->setTransactionAdditionalInfo(
			self::KEY_AMOUNT,
			SubjectReader::getValueSafely($paypalResponse, "total", $this->amountPath)
		);
	}
}
