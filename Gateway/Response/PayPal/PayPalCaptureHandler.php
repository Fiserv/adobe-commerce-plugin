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
 * Class PayPalCaptureHandler
 */
class PayPalCaptureHandler implements HandlerInterface
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

		$paypalResponseData = $this->subjectReader->readPayPalResponse($response);
		if (isset($paypalResponseData)) {
			$paypalResponse = $paypalResponseData;
			$this->logger->logInfo(2, 'PayPal Capture Handler Response'. json_encode($paypalResponse));
		} else {
			$this->logger->logError(2, 'PayPal Capture Handler Response Error:');
		}

		$tnxDetails = $paypalResponse["gatewayResponse"]["transactionProcessingDetails"];

		$transId = $tnxDetails["transactionId"];
		$payment->setLastTransId($transId);
		$payment->setTransactionId($transId);

		// For capture, we need to close the parent transaction
		$payment->setShouldCloseParentTransaction(true);
		$payment->setIsTransactionClosed(true);

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

		// Add transaction record for capture
		$payment->addTransaction(Transaction::TYPE_CAPTURE, null, true, __('PayPal Capture'));
	}
}
