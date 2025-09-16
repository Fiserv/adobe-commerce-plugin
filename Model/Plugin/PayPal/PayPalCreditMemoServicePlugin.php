<?php
namespace Fiserv\Payments\Model\Plugin\PayPal;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Service\CreditmemoService;
use Fiserv\Payments\Model\Service\CommerceHub\FailedOrderManager;
use Fiserv\Payments\Model\Service\PayPal\FailedTransactionManager;
use Fiserv\Payments\Gateway\Http\PayPal\Client\HttpClient;

class PayPalCreditMemoServicePlugin
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
		try{
			$result = $proceed($creditmemo, $offlineRequested);
			return $result;
		}
		catch (\Exception $e)
		{
			$order = $creditmemo->getOrder();
			$failedOrder = $this->failedOrderManager->createFailedOrder($order);
			$this->failedOrderManager->saveFailedOrder($failedOrder, $order->getQuoteId(), $order->getStoreId());

			$payment = $order->getPayment();
			$data = $payment->getAdditionalInformation('paypal_failed_transaction_data');

			$data['response'][HttpClient::RESPONSE_KEY]["amount"] = ($creditmemo['grand_total']) ? $creditmemo['grand_total'] : 0;

			if ($data) {
				$this->failedTransactionManager->createFailedTransaction(
					$order['increment_id'],
					$data['response'],
					$data['paths'],
					$data['payment_action']
				);
			}

			throw $e;
		}
	}
}
