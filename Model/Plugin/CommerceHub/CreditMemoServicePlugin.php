<?php
namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Service\CreditmemoService;
use Fiserv\Payments\Model\Service\CommerceHub\FailedOrderManager;
use Fiserv\Payments\Model\Service\CommerceHub\FailedTransactionManager;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;

class CreditMemoServicePlugin
{
	protected $failedOrderManager;
	protected $failedTransactionManager;

	public function __construct(
		FailedOrderManager $failedOrderManager,
		FailedTransactionManager $failedTransactionManager
	) {
		$this->failedOrderManager = $failedOrderManager;
		$this->failedTransactionManager = $failedTransactionManager;
	}

	public function aroundRefund(CreditmemoService $subject, callable $proceed, Creditmemo $creditmemo, $offlineRequested = false)
	{
		try {
			return $proceed($creditmemo, $offlineRequested);
		} catch (\Exception $e) {
			$order = $creditmemo->getOrder();
			$failedOrder = $this->failedOrderManager->createFailedOrder($order);
			$this->failedOrderManager->saveFailedOrder($failedOrder, $order->getQuoteId(), $order->getStoreId());

			$payment = $order->getPayment();
			$data = $payment ? $payment->getAdditionalInformation('ch_failed_transaction_data') : null;

			if (is_array($data)
				&& isset($data['response'], $data['paths'], $data['payment_action'])
				&& isset($data['response'][HttpClient::RESPONSE_KEY])
			) {
				$data['response'][HttpClient::RESPONSE_KEY]['amount'] = $creditmemo->getGrandTotal() ?: 0;

				$this->failedTransactionManager->createFailedTransaction(
					$order->getIncrementId(),
					$data['response'],
					$data['paths'],
					$data['payment_action']
				);
			}

			throw $e;
		}
	}
}