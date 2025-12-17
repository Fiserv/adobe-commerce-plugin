<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Fiserv\Payments\Api\SubscriptionOrder\SubscriptionOrderRepositoryInterface;
use Fiserv\Payments\Model\Subscription\OrderFactory as SubscriptionOrderFactory;
use Fiserv\Payments\Api\Data\SubscriptionOrder\SubscriptionOrderInterface;

class PaymentDetailsHandler implements HandlerInterface
{
	const API_TRACE_ID = "apiTraceId";
	const KEY_AMOUNT = "amount";
	const KEY_ORDER_ID = "orderId";
	const TXN_TIMESTAMP = "txnTimestamp";

	/**
	 * @var SubjectReader
	 */
	protected $subjectReader;

	private SubscriptionOrderRepositoryInterface $subscriptionOrderRepository;
	private SubscriptionOrderFactory $subscriptionOrderFactory;

	/**
	 * Constructor
	 *
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(
		SubjectReader $subjectReader,
		SubscriptionOrderRepositoryInterface $subscriptionOrderRepository,
		SubscriptionOrderFactory $subscriptionOrderFactory
	) {
		$this->subjectReader = $subjectReader;
		$this->subscriptionOrderRepository = $subscriptionOrderRepository;
		$this->subscriptionOrderFactory = $subscriptionOrderFactory;
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $handlingSubject, array $response)
	{
		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();

		$chResponse = $this->subjectReader->readChResponse($response)
		[\Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient::RESPONSE_KEY];

		$tnxDetails = $chResponse["gatewayResponse"]["transactionProcessingDetails"];

		$transId = (string)($tnxDetails["transactionId"] ?? '');
		if ($transId !== '') {
			$payment->setLastTransId($transId);
			$payment->setTransactionId($transId);
		}

		$payment->setShouldCloseParentTransaction(false);
		$payment->setIsTransactionClosed(false);

		$payment->setTransactionAdditionalInfo(self::API_TRACE_ID, $tnxDetails["apiTraceId"] ?? null);
		$payment->setTransactionAdditionalInfo(self::KEY_ORDER_ID, $tnxDetails["orderId"] ?? null);

		$amount = null;
		if (isset($chResponse["transaction"]["paymentReceipt"]["approvedAmount"])) {
			$amount = $chResponse["transaction"]["paymentReceipt"]["approvedAmount"]['total'] ?? null;
		} elseif (isset($chResponse["paymentReceipt"]["approvedAmount"])) {
			$amount = $chResponse["paymentReceipt"]["approvedAmount"]['total'] ?? null;
		}
		if ($amount !== null) {
			$payment->setTransactionAdditionalInfo(self::KEY_AMOUNT, (float)$amount);
		}

		// Best-effort: persist the gateway transactionId into subscription_order row for this order_increment_id
		try {
			$orderIncrementId = '';
			if ($orderDO) {
				$orderIncrementId = (string)(
				(method_exists($orderDO, 'getOrderIncrementId') ? $orderDO->getOrderIncrementId() : '')
					?: (method_exists($orderDO, 'getIncrementId') ? $orderDO->getIncrementId() : '')
				);
			}

			if ($orderIncrementId !== '' && $transId !== '') {
				$sub = $this->subscriptionOrderFactory->create()->load(
					$orderIncrementId,
					SubscriptionOrderInterface::ORDER_INCREMENT_ID
				);

				if ($sub && $sub->getEntityId()) {
					$sub->setLastGatewayTransactionId($transId);
					$this->subscriptionOrderRepository->save($sub);
				}
			}
		} catch (\Throwable) {}
	}
}