<?php
declare(strict_types=1);

namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Fiserv\Payments\Api\SubscriptionOrder\SubscriptionOrderRepositoryInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Subscription\OrderFactory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class SubscriptionDetailsHandler implements HandlerInterface
{
	public function __construct(
		private readonly MultiLevelLogger $logger,
		private readonly SubscriptionOrderRepositoryInterface $subscriptionOrderRepository,
		private readonly OrderFactory $orderFactory
	) {}

	public function handle(array $handlingSubject, array $response)
	{
		if (empty($handlingSubject['payment']) || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface) {
			return;
		}

		$paymentDO = $handlingSubject['payment'];
		$payment = $paymentDO->getPayment();
		$order = $paymentDO->getOrder();

		$response = $this->unwrapResponse($response);
		if (!is_array($response)) {
			return;
		}

		$gatewayTransactionId = $this->firstPath($response, [
			['gatewayResponse', 'transactionProcessingDetails', 'transactionId'],
			['gatewayResponse', 'transactionProcessingDetails', 'apiTraceId'],
		]);

		$schemeRefId = $this->firstPath($response, [
			['storedCredentials', 'schemeReferenceTransactionId'],
			['gatewayResponse', 'storedCredentials', 'schemeReferenceTransactionId'],
			['paymentReceipt', 'storedCredentials', 'schemeReferenceTransactionId'],
			['gatewayResponse', 'transactionDetails', 'storedCredentials', 'schemeReferenceTransactionId'],
			['schemeReferenceTransactionId'],
		]);

		$gatewaySequence = $this->firstPath($response, [
			['storedCredentials', 'sequence'],
			['gatewayResponse', 'storedCredentials', 'sequence'],
			['paymentReceipt', 'storedCredentials', 'sequence'],
			['gatewayResponse', 'transactionDetails', 'storedCredentials', 'sequence'],
			['sequence'],
		]);

		$tokenData = $this->getPath($response, ['paymentTokens', 0, 'tokenData']);
		$tokenSource = $this->getPath($response, ['paymentTokens', 0, 'tokenSource']);
		$intervalValue = $this->getPath($response, ['subscriptionInterval', 'value']);
		$intervalUnit = $this->getPath($response, ['subscriptionInterval', 'unit']);

		// Only treat this as a subscription if the response explicitly signals recurring billing.
		// A plain charge that happens to tokenize should NOT become a subscription row.
		$billPaymentType = strtoupper((string)$this->firstPath($response, [
			['additionalDataCommon', 'billPaymentType'],
			['billPaymentType'],
		]) ?? '');

		$isRecurringResponse = ($billPaymentType === 'RECURRING') && !empty($gatewaySequence);

		if (empty($schemeRefId) && empty($tokenData) && empty($gatewayTransactionId) && empty($tokenSource)) {
			// nothing useful to persist
			return;
		}

		[$expMonth, $expYear] = $this->extractExpiryFromResponse($response);

		if (!$isRecurringResponse) {
			// Not a subscription/recurring transaction — persist token data to payment but
			// do NOT flag is_subscription or create a subscription row.
			try {
				if (!empty($tokenData)) {
					$payment->setAdditionalInformation('payment_token', $tokenData);
				}
				if (!empty($tokenSource)) {
					$payment->setAdditionalInformation('token_source', $tokenSource);
				}
				if (!empty($schemeRefId)) {
					$payment->setAdditionalInformation('scheme_reference_transaction_id', $schemeRefId);
					$payment->setAdditionalInformation('schemeReferenceTransactionId', $schemeRefId);
				}
				if ($expMonth) {
					$payment->setAdditionalInformation('expiration_month', $expMonth);
				}
				if ($expYear) {
					$payment->setAdditionalInformation('expiration_year', $expYear);
				}
			} catch (\Throwable $e) {
				$this->logger->logDebug(2, 'CommerceHub: failed to attach token data to non-subscription payment: ' . $e->getMessage());
			}
			return;
		}

		// Attach to payment additionalInformation (best-effort)
		try {
			if (!empty($schemeRefId)) {
				$payment->setAdditionalInformation('scheme_reference_transaction_id', $schemeRefId);
				$payment->setAdditionalInformation('schemeReferenceTransactionId', $schemeRefId);
			}

			if (!empty($gatewaySequence)) {
				$payment->setAdditionalInformation('gateway_sequence', $gatewaySequence);
			}

			// Only flag as subscription when the response explicitly confirms recurring billing
			$payment->setAdditionalInformation('is_subscription', true);

			if (!empty($tokenData)) {
				$payment->setAdditionalInformation('payment_token', $tokenData);
			}
			if (!empty($tokenSource)) {
				$payment->setAdditionalInformation('token_source', $tokenSource);
			}
			if ($intervalValue !== null) {
				$payment->setAdditionalInformation('subscription_interval_value', $intervalValue);
			}
			if ($intervalUnit !== null) {
				$payment->setAdditionalInformation('subscription_interval_unit', $intervalUnit);
			}
			if ($expMonth) {
				$payment->setAdditionalInformation('expiration_month', $expMonth);
			}
			if ($expYear) {
				$payment->setAdditionalInformation('expiration_year', $expYear);
			}
		} catch (\Throwable $e) {
			$this->logger->logDebug(2, 'CommerceHub: failed to attach subscription data to payment: ' . $e->getMessage());
		}

		// Determine THIS Magento order increment id (authoritative)
		$canonicalOrderIncrementId = null;

		if ($order) {
			$canonicalOrderIncrementId =
				$this->getOrderValue($order, 'getOrderIncrementId', 'order_increment_id')
					?: $this->getOrderValue($order, 'getIncrementId', 'increment_id');
		}

		if (empty($canonicalOrderIncrementId)) {
			$canonicalOrderIncrementId = $this->firstPath($response, [
				['transactionDetails', 'merchantOrderId'],
				['merchantOrderId'],
			]);
		}

		if (empty($canonicalOrderIncrementId)) {
			$this->logger->logDebug(1, 'CommerceHub: cannot determine order_increment_id for subscription row.');
			return;
		}

		$orderIncrementId = (string)$canonicalOrderIncrementId;

		// ---- Backfill interval from payment additional info ----
		try {
			$paymentIntervalValue = $payment->getAdditionalInformation('subscription_interval_value');
			$paymentIntervalUnit = $payment->getAdditionalInformation('subscription_interval_unit');

			if (($intervalValue === null || $intervalValue === '') && $paymentIntervalValue !== null && $paymentIntervalValue !== '') {
				$intervalValue = $paymentIntervalValue;
			}
			if (($intervalUnit === null || $intervalUnit === '') && $paymentIntervalUnit !== null && $paymentIntervalUnit !== '') {
				$intervalUnit = $paymentIntervalUnit;
			}
		} catch (\Throwable) {
			// ignore
		}

		$rootIncrement = $this->rootFromIncrementId($orderIncrementId);
		$computedSequence = $this->isSubsequentIncrementId($orderIncrementId) ? 'SUBSEQUENT' : 'FIRST';

		try {
			$payment->setAdditionalInformation('original_order_increment', $rootIncrement);
			$payment->setAdditionalInformation('sequence', $computedSequence);
		} catch (\Throwable) {
			// ignore
		}

		try {
			$subscription = $this->orderFactory->create()->load($orderIncrementId, 'order_increment_id');

			$isNew = (!$subscription || !$subscription->getEntityId());
			if ($isNew) {
				$subscription = $this->orderFactory->create();
				$subscription->setData('order_increment_id', $orderIncrementId);
			}

			$subscription->setData('original_order_increment', $rootIncrement);
			$subscription->setData('sequence', $computedSequence);

			/**
			 * Ensure scheme_reference_transaction_id is persisted even when the gateway response omits it:
			 * - prefer response
			 * - else keep existing DB value
			 * - else use payment additional info (set by SubscriptionProcessor from chain head)
			 */
			$finalSchemeRef = (string)($schemeRefId ?? '');
			if ($finalSchemeRef === '') {
				$existing = (string)($subscription->getData('scheme_reference_transaction_id') ?? '');
				if ($existing !== '') {
					$finalSchemeRef = $existing;
				}
			}
			if ($finalSchemeRef === '') {
				try {
					$schemeRefFromPayment = $payment->getAdditionalInformation('scheme_reference_transaction_id')
						?: $payment->getAdditionalInformation('schemeReferenceTransactionId');
					if (is_scalar($schemeRefFromPayment) && (string)$schemeRefFromPayment !== '') {
						$finalSchemeRef = (string)$schemeRefFromPayment;
					}
				} catch (\Throwable) {
					// ignore
				}
			}
			if ($finalSchemeRef !== '') {
				$subscription->setData('scheme_reference_transaction_id', $finalSchemeRef);
			}

			if (!empty($tokenData)) { $subscription->setData('payment_token', $tokenData); }
			if (!empty($tokenSource)) { $subscription->setData('token_source', $tokenSource); }

			if (!empty($gatewayTransactionId)) {
				$subscription->setData('last_gateway_transaction_id', (string)$gatewayTransactionId);
			} else {
				$this->logger->logDebug(2, 'SubscriptionDetailsHandler: gatewayTransactionId missing', "Order ID: $orderIncrementId");
			}

			if ($intervalValue !== null && $intervalValue !== '') { $subscription->setData('interval_value', (int)$intervalValue); }
			if ($intervalUnit !== null && $intervalUnit !== '') { $subscription->setData('interval_unit', (string)$intervalUnit); }

			if ($expMonth) { $subscription->setData('expiration_month', $expMonth); }
			if ($expYear) { $subscription->setData('expiration_year', $expYear); }

			if (
				!$subscription->getData('next_billing_datetime')
				&& $subscription->getData('interval_value')
				&& $subscription->getData('interval_unit')
			) {
				$subscription->setData(
					'next_billing_datetime',
					$this->computeNextBillingDatetime(
						(int)$subscription->getData('interval_value'),
						(string)$subscription->getData('interval_unit')
					)
				);
			}


			$this->subscriptionOrderRepository->save($subscription);
		} catch (\Throwable $e) {
			$this->logger->logDebug(2, 'CommerceHub SubscriptionDetailsHandler error: ' . $e->getMessage());
		}
	}

	private function isSubsequentIncrementId(string $incrementId): bool
	{
		return (bool)preg_match('/-\d+$/', $incrementId);
	}

	private function rootFromIncrementId(string $incrementId): string
	{
		return preg_replace('/-\d+$/', '', $incrementId) ?: $incrementId;
	}

	private function computeNextBillingDatetime(int $intervalValue, string $intervalUnit): string
	{
		$normalizedValue = max(1, $intervalValue);
		$normalizedUnit = strtolower(trim($intervalUnit));

		$normalizedUnit = match (true) {
			in_array($normalizedUnit, ['min', 'mins', 'minute', 'minutes'], true) => 'minute',
			in_array($normalizedUnit, ['day', 'days', 'd'], true) => 'day',
			in_array($normalizedUnit, ['week', 'weeks', 'w', 'weekly'], true) => 'week',
			in_array($normalizedUnit, ['month', 'months', 'monthly'], true) => 'month',
			in_array($normalizedUnit, ['year', 'years', 'y', 'annual', 'yearly'], true) => 'year',
			default => 'minute',
		};

		$nowUtc = new \DateTime('now', new \DateTimeZone('UTC'));
		if ($normalizedUnit === 'minute') {
			$seconds = $normalizedValue * 60;
			return $nowUtc->modify("+{$seconds} seconds")->format('Y-m-d H:i:s');
		}

		return $nowUtc->modify(sprintf('+%d %s', $normalizedValue, $normalizedUnit))->format('Y-m-d H:i:s');
	}

	private function unwrapResponse(array $response): ?array
	{
		if (!isset($response['response'])) {
			return $response;
		}

		$inner = $response['response'];
		if (is_string($inner)) {
			$decoded = json_decode($inner, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				return $decoded;
			}
			$this->logger->logDebug(1, 'CommerceHub: failed to json_decode response wrapper', json_last_error_msg());
			return null;
		}

		if (is_array($inner)) {
			return $inner;
		}

		$this->logger->logDebug(1, 'CommerceHub: unexpected response wrapper type', gettype($inner));
		return null;
	}

	private function getPath(array $arr, array $path)
	{
		$cur = $arr;
		foreach ($path as $k) {
			if (!is_array($cur) || !array_key_exists($k, $cur)) {
				return null;
			}
			$cur = $cur[$k];
		}
		return $cur;
	}

	private function firstPath(array $arr, array $paths): ?string
	{
		foreach ($paths as $path) {
			$val = $this->getPath($arr, $path);
			if ($val !== null && $val !== '') {
				return (string)$val;
			}
		}
		return null;
	}

	private function getOrderValue($order, string $method, ?string $dataKey = null)
	{
		if (!is_object($order)) {
			return null;
		}
		if (method_exists($order, $method)) {
			try {
				return $order->{$method}();
			} catch (\Throwable) {
				// ignore
			}
		}
		if ($dataKey !== null && method_exists($order, 'getData')) {
			try {
				return $order->getData($dataKey);
			} catch (\Throwable) {
				return null;
			}
		}
		return null;
	}

	private function extractExpiryFromResponse(array $response): array
	{
		$candidates = [$response];

		if (!empty($response['source']) && is_array($response['source']) && !empty($response['source']['card']) && is_array($response['source']['card'])) {
			$candidates[] = $response['source']['card'];
		}
		if (!empty($response['card']) && is_array($response['card'])) {
			$candidates[] = $response['card'];
		}
		if (!empty($response['paymentTokens']) && is_array($response['paymentTokens']) && count($response['paymentTokens']) > 0) {
			$first = $response['paymentTokens'][0];
			if (is_array($first)) {
				$candidates[] = $first;
				if (!empty($first['card']) && is_array($first['card'])) {
					$candidates[] = $first['card'];
				}
			}
		}

		$month = null;
		$year = null;

		foreach ($candidates as $candidate) {
			if (!is_array($candidate)) { continue; }

			if (!$month) {
				$month = $candidate['expirationMonth'] ?? $candidate['exp_month'] ?? $candidate['expiration_month'] ?? $candidate['expMonth'] ?? null;
				if ($month !== null) {
					$month = str_pad((string)((int)$month), 2, '0', STR_PAD_LEFT);
				}
			}

			if (!$year) {
				$year = $candidate['expirationYear'] ?? $candidate['exp_year'] ?? $candidate['expiration_year'] ?? $candidate['expYear'] ?? null;
				if ($year !== null) {
					$year = (string)$year;
					if (preg_match('/^\d{2}$/', $year)) {
						$year = '20' . $year;
					}
				}
			}

			if (!$year && !empty($candidate['expiration']) && is_string($candidate['expiration'])) {
				$parts = preg_split('/[\/\-]/', $candidate['expiration']);
				if (count($parts) >= 2) {
					$month = $month ?: str_pad((string)((int)$parts[0]), 2, '0', STR_PAD_LEFT);
					$year = $year ?: (string)$parts[1];
					if ($year && preg_match('/^\d{2}$/', $year)) {
						$year = '20' . $year;
					}
				}
			}

			if ($month && $year) {
				break;
			}
		}

		return [$month ?: null, $year ?: null];
	}
}