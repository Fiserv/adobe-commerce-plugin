<?php

namespace Fiserv\Payments\Model\Plugin\PayPal;

use Fiserv\Payments\Model\Service\PayPal\FailedOrderManager;
use Magento\Sales\Model\Order;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\PayPal\ConfigProvider;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

class PayPalOrderPlace
{
	protected $logger;
	protected $failedOrderManager;
	protected $transactionRepository;

	public function __construct(
		MultiLevelLogger $logger,
		FailedOrderManager $failedOrderManager,
		TransactionRepositoryInterface $transactionRepository
	) {
		$this->logger = $logger;
		$this->failedOrderManager = $failedOrderManager;
		$this->transactionRepository = $transactionRepository;
	}

	public function aroundPlace(Order $order, callable $proceed)
	{
		try
		{
			$result = $proceed();

			// Add transaction record for PayPal payments
			$payment = $order->getPayment();
			if ($payment->getMethod() === ConfigProvider::CODE && $payment->getLastTransId()) {
				try {
					$transaction = $this->transactionRepository->getByTransactionId(
						$payment->getLastTransId(),
						$payment->getId(),
						$order->getId()
					);
				} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
					$transaction = null;
				}

				if (!$transaction) {
					$paymentAction = $payment->getMethodInstance()->getConfigData('payment_action');
					if ($paymentAction === 'authorize') {
						$payment->addTransaction(Transaction::TYPE_AUTH, null, false, __('PayPal Authorization'));
					} elseif ($paymentAction === 'sale') {
						$payment->addTransaction(Transaction::TYPE_CAPTURE, null, true, __('PayPal Sale'));
					}
				}
			}

			return $result;
		}
		catch (\Exception $e)
		{
			$failedOrder = $this->failedOrderManager->createFailedOrder($order);
			$this->failedOrderManager->saveFailedOrder($failedOrder, $order->getQuoteId(), $order->getStoreId());
			throw $e;
		}
	}

}

