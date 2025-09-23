<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\PayPal;

use Fiserv\Payments\Gateway\Subject\PayPal\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class PaymentDetailsHandler
 */
class PayPalPaymentDetailsHandler implements HandlerInterface
{
	//TODO: Confirm if this is the correct file exactly needed or not.
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
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param SubjectReader $subjectReader
	 * @param MultiLevelLogger $logger
	 */
	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $handlingSubject, array $response)
	{
		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();

		$paypalResponse = $this->subjectReader->readPayPalResponse($response)[\Fiserv\Payments\Gateway\Http\PayPal\Client\HttpClient::RESPONSE_KEY];

		$this->logger->logInfo(1, "PayPal Response: " . json_encode($paypalResponse));

		$tnxDetails = $paypalResponse["gatewayResponse"]["transactionProcessingDetails"];

		$transId = $tnxDetails["transactionId"];
		$this->logger->logInfo(1, "Transaction ID: " . ($transId ?? "Not found"));
		$payment->setLastTransId($transId);
		$payment->setTransactionId($transId);

		$payment->setShouldCloseParentTransaction(false);
		$payment->setIsTransactionClosed(false);

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

		// Set amount paid for proper refund button visibility
		$approvedAmount = SubjectReader::getValueSafely($paypalResponse, "total", $this->amountPath);
		if ($approvedAmount > 0) {
			$payment->setAmountPaid($approvedAmount);
			$payment->setBaseAmountPaid($approvedAmount);
		}

		$this->logger->logInfo(1, "Payment Details Handler completed successfully.", json_encode($payment->getData(), JSON_PRETTY_PRINT));
	}
}
