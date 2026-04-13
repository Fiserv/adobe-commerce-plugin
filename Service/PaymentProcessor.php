<?php
declare(strict_types=1);
namespace Fiserv\Payments\Service;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Subscription\Order as SubscriptionOrder;
use Fiserv\Payments\Observer\CommerceHub\DataAssignObserver;
use Fiserv\Payments\Service\VaultTokenDetailsParser;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Quote\Model\Quote;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Throwable;

class PaymentProcessor
{
	public function __construct(
		private readonly PaymentTokenRepositoryInterface $paymentTokenRepository,
		private readonly PaymentTokenManagementInterface $paymentTokenManagement,
		private readonly CommandPoolInterface            $commandPool,
		private readonly PaymentDataObjectFactory        $paymentDataObjectFactory,
		private readonly MultiLevelLogger                $logger,
		private readonly OrderPaymentContextBuilder      $orderPaymentContextBuilder,
		private readonly ExtensionAttributesFactory      $extensionAttributesFactory,
		private readonly VaultTokenDetailsParser         $tokenDetailsParser
	)
	{
	}

	public function processSubscriptionPayment(Quote $quote, SubscriptionOrder $subscription)
	{
		try {
			$this->logger->logInfo(1, 'processSubscriptionPayment: started', "Subscription ID: {$subscription->getId()}");

			$payment = $quote->getPayment();
			if (!$payment) {
				throw new \Exception('Quote has no payment object');
			}

			$schemeReferenceTransactionId = $payment->getAdditionalInformation('scheme_reference_transaction_id');
			$vaultPublicHash = $payment->getAdditionalInformation('public_hash');
			$isSubscriptionPayment = $payment->getAdditionalInformation('is_subscription');

			if (!$schemeReferenceTransactionId) {
				throw new \Exception('Missing scheme reference transaction ID');
			}
			if (!$vaultPublicHash) {
				throw new \Exception('Missing payment token (public_hash)');
			}
			if (!$isSubscriptionPayment) {
				throw new \Exception('Payment not marked as subscription');
			}

			$orderGrandTotal = (float)$quote->getGrandTotal();
			if ($orderGrandTotal <= 0) {
				throw new \Exception('Invalid grand total for subscription payment: ' . $orderGrandTotal);
			}


			$vaultToken = $this->retrievePaymentToken($vaultPublicHash, $quote->getCustomerId());
			if (!$vaultToken || !($vaultToken instanceof PaymentTokenInterface) || !$vaultToken->getIsActive()) {
				throw new \Exception('Payment token not found or inactive in vault for public_hash: ' . $vaultPublicHash);
			}

			if (!$payment->getMethod()) {
				$payment->setMethod('commercehub');
			}

		$merchantOrderId = $payment->getAdditionalInformation('merchant_order_id')
			?: ($payment->getExtensionAttributes() && method_exists($payment->getExtensionAttributes(), 'getMerchantOrderId')
				? $payment->getExtensionAttributes()->getMerchantOrderId()
				: null);

		if (!$merchantOrderId) {
			$merchantOrderId = $subscription->getOrderIncrementId();
		}

		if ($merchantOrderId) {
				$payment->setAdditionalInformation('merchantOrderId', $merchantOrderId);
			}

			$paymentExtension = $payment->getExtensionAttributes()
				?: $this->extensionAttributesFactory->create(\Magento\Quote\Api\Data\PaymentInterface::class);

			try {
				if (is_object($paymentExtension) && method_exists($paymentExtension, 'setVaultPaymentToken')) {
					$paymentExtension->setVaultPaymentToken($vaultToken);
				}
			} catch (Throwable) {
				// ignore
			}

			try {
				if ($merchantOrderId && is_object($paymentExtension) && method_exists($paymentExtension, 'setMerchantOrderId')) {
					$paymentExtension->setMerchantOrderId($merchantOrderId);
				}
			} catch (Throwable) {
				// ignore
			}

			$payment->setExtensionAttributes($paymentExtension);

			$gatewayToken = null;
			try {
				$gatewayToken = method_exists($vaultToken, 'getGatewayToken') ? $vaultToken->getGatewayToken() : null;
			} catch (Throwable) {
				$gatewayToken = null;
			}
			if (!$gatewayToken) {
				throw new \Exception('Vault token is missing gateway token');
			}

			$payment->setAdditionalInformation(DataAssignObserver::PAYMENT_TOKEN_KEY, $gatewayToken);
			$payment->setAdditionalInformation('payment_token', $gatewayToken);
			$payment->setAdditionalInformation('gateway_token', $gatewayToken);

		try {
			$tokenDetailsRaw = method_exists($vaultToken, 'getTokenDetails') ? $vaultToken->getTokenDetails() : null;
				$tokenSource = $this->tokenDetailsParser->extractTokenSource($tokenDetailsRaw);
				if ($tokenSource) {
					$payment->setAdditionalInformation(DataAssignObserver::TOKEN_SOURCE_KEY, $tokenSource);
				}
			} catch (Throwable) {
				// ignore
			}

			$this->populateExpiryFromVaultOnly($payment, $vaultToken);

		$finalExpiryMonth = $payment->getAdditionalInformation(DataAssignObserver::EXP_MONTH_KEY) ?: null;
		$finalExpiryYear = $payment->getAdditionalInformation(DataAssignObserver::EXP_YEAR_KEY) ?: null;
		$resolvedExpiryMonth = $finalExpiryMonth ? ($this->tokenDetailsParser->normalizeMonth($finalExpiryMonth) ?: $finalExpiryMonth) : null;
		$resolvedExpiryYear = $finalExpiryYear ? ($this->tokenDetailsParser->normalizeYear($finalExpiryYear) ?: $finalExpiryYear) : null;

			$this->logger->logInfo(1, 'processSubscriptionPayment: executing transaction');

			$orderPayment = null;
			if ($this->quotePaymentNeedsOrderContext($payment)) {
				$orderPayment = $this->orderPaymentContextBuilder->createOrderPaymentFromQuote(
					$quote,
					$payment,
					$payment->getAdditionalInformation('merchant_order_id') ?: null
				);
				$paymentDataObject = $this->paymentDataObjectFactory->create($orderPayment);
			} else {
				$paymentDataObject = $this->paymentDataObjectFactory->create($payment);
			}

			$commandSubject = [
				'payment' => $paymentDataObject,
				'amount' => $orderGrandTotal,
				'subscription' => $subscription,
			];

			if ($merchantOrderId) {
				$commandSubject['merchantOrderId'] = $merchantOrderId;
			}

			$gatewayResult = $this->commandPool->get('vault_authorize')->execute($commandSubject);

			// PaymentDetailsHandler sets the transaction ID on $orderPayment (not the quote $payment).
			// Read from $orderPayment first, then fall back to $payment, then extractTransactionIdFromResult.
			$transactionId = null;
			if ($orderPayment && method_exists($orderPayment, 'getLastTransId')) {
				$transactionId = $orderPayment->getLastTransId() ?: null;
			}
			if (!$transactionId) {
				$transactionId = $payment->getLastTransId() ?: null;
			}
			if (!$transactionId) {
				$transactionId = $this->extractTransactionIdFromResult($gatewayResult);
			}

			if ($transactionId) {
				$payment->setLastTransId($transactionId);
				if ($orderPayment) {
					$orderPayment->setAdditionalInformation('last_trans_id', $transactionId);
					if (method_exists($orderPayment, 'setLastTransId')) {
						$orderPayment->setLastTransId($transactionId);
					}
				}
			}

			return [
				'success' => true,
				'transaction_id' => $transactionId,
				'result' => $gatewayResult,
				'expiration_month' => $resolvedExpiryMonth,
				'expiration_year' => $resolvedExpiryYear,
			];
		} catch (Throwable $e) {
			$this->logger->logError(1, 'Subscription payment failed', "Subscription ID: {$subscription->getId()}, Error: {$e->getMessage()}");
			return ['success' => false, 'error' => $e->getMessage()];
		}
	}

	private function populateExpiryFromVaultOnly($payment, PaymentTokenInterface $vaultToken): void
	{
		$tokenDetailsRaw = null;
		try {
			$tokenDetailsRaw = method_exists($vaultToken, 'getTokenDetails') ? $vaultToken->getTokenDetails() : null;
		} catch (Throwable) {
			$tokenDetailsRaw = null;
		}

		[$expiryMonth, $expiryYear] = $this->tokenDetailsParser->extractExpiry($tokenDetailsRaw);

		if ($expiryMonth) {
			$payment->setAdditionalInformation(DataAssignObserver::EXP_MONTH_KEY, $expiryMonth);
			$payment->setAdditionalInformation('expiration_month', $expiryMonth);
		}
		if ($expiryYear) {
			$payment->setAdditionalInformation(DataAssignObserver::EXP_YEAR_KEY, $expiryYear);
			$payment->setAdditionalInformation('expiration_year', $expiryYear);
		}
	}

	private function retrievePaymentToken($vaultPublicHash, $customerId = null)
	{
		try {
			$token = $this->paymentTokenManagement->getByPublicHash($vaultPublicHash, $customerId);
			if ($token instanceof PaymentTokenInterface) {
				return $token;
			}
		} catch (Throwable $e) {
			$this->logger->logDebug(2, 'paymentTokenManagement->getByPublicHash failed', $e->getMessage());
		}

		try {
			$token = $this->paymentTokenRepository->getByPublicHash($vaultPublicHash, $customerId);
			if ($token instanceof PaymentTokenInterface) {
				return $token;
			}
		} catch (Throwable $e) {
			$this->logger->logDebug(2, 'paymentTokenRepository->getByPublicHash failed', $e->getMessage());
		}

		return null;
	}

	private function quotePaymentNeedsOrderContext($payment): bool
	{
		try {
			return !(method_exists($payment, 'getOrder') && $payment->getOrder());
		} catch (Throwable) {
			return true;
		}
	}

	private function extractTransactionIdFromResult($gatewayResult)
	{
		if (empty($gatewayResult)) {
			return null;
		}
		if (is_array($gatewayResult)) {
			foreach (['transaction_id', 'transactionId', 'id', 'orderId', 'clientRequestId'] as $key) {
				if (!empty($gatewayResult[$key]) && is_scalar($gatewayResult[$key])) {
					return (string)$gatewayResult[$key];
				}
			}
			if (!empty($gatewayResult['gatewayResponse']) && is_array($gatewayResult['gatewayResponse'])) {
				$gatewayResponseData = $gatewayResult['gatewayResponse'];
				if (!empty($gatewayResponseData['transactionProcessingDetails'])
					&& is_array($gatewayResponseData['transactionProcessingDetails'])
					&& !empty($gatewayResponseData['transactionProcessingDetails']['transactionId'])
				) {
					return (string)$gatewayResponseData['transactionProcessingDetails']['transactionId'];
				}
			}
		}
		return null;
	}

}

