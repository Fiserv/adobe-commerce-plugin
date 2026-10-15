<?php
namespace Fiserv\Payments\Gateway\Response\CashApp;

use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\CashApp\SessionPolicy;
use Fiserv\Payments\Model\CashApp\TokenExtractor;
use Fiserv\Payments\Model\CashApp\TokenStorage;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class TokenDetailsHandler implements HandlerInterface
{
	private $reader;
	private $quotes;
	private $storage;
	private $extractor;
	private $logger;
	private $messages;
	private $stores;

	public function __construct(
		SubjectReader $reader,
		CartRepositoryInterface $quotes,
		TokenStorage $storage,
		TokenExtractor $extractor,
		MultiLevelLogger $logger,
		ManagerInterface $messages,
		StoreManagerInterface $stores
	) {
		$this->reader = $reader;
		$this->quotes = $quotes;
		$this->storage = $storage;
		$this->extractor = $extractor;
		$this->logger = $logger;
		$this->messages = $messages;
		$this->stores = $stores;
	}

	public function handle(array $handlingSubject, array $response)
	{
		$payment = $this->reader->readPayment($handlingSubject)->getPayment();
		if ($payment->getMethod() !== 'fiserv_cashapp') {
			return;
		}
		if (!in_array($payment->getAdditionalInformation('cashapp_save_payment_method'), [true, 1, '1', 'true'], true)) {
			$this->logOutcome($payment, 'skipped_no_opt_in');
			return;
		}
		try {
			$order = $payment->getOrder();
			$customerId = (int) $order->getCustomerId();
			if ($customerId <= 0) {
				$this->logOutcome($payment, 'skipped_guest');
				return;
			}
			if ($this->storage->hasForCustomer($customerId)) {
				$payment->setAdditionalInformation('cashapp_tokenization_status', 'already_saved');
				$this->logOutcome($payment, 'already_saved');
				return;
			}
			$quote = $this->quotes->get((int) $order->getQuoteId());
			$snapshot = $quote->getPayment()->getAdditionalInformation(SessionPolicy::SNAPSHOT);
			if (!is_array($snapshot)) {
				$this->notSaved($payment, 'missing_session_snapshot');
				return;
			}
			if (($snapshot['can_save'] ?? false) !== true) {
				$this->notSaved($payment, 'session_not_eligible_for_saving');
				return;
			}
			$body = $this->reader->readChResponse($response)[HttpClient::RESPONSE_KEY];
			$sessionId = $payment->getAdditionalInformation('payment_session');
			$scope = $snapshot['scope'] ?? [];
			if ((int) $quote->getCustomerId() !== $customerId
				|| (int) ($snapshot['customer_id'] ?? 0) !== $customerId
				|| (int) ($snapshot['quote_id'] ?? 0) !== (int) $order->getQuoteId()
				|| (int) $quote->getStoreId() !== (int) $order->getStoreId()
				|| (int) ($scope['website_id'] ?? -1) !== (int) $this->stores->getStore($order->getStoreId())->getWebsiteId()
				|| !is_string($sessionId) || $sessionId === ''
				|| $sessionId !== ($snapshot['session_id'] ?? null)
				|| $sessionId !== ($body['session']['sessionId'] ?? null)
				|| ($body['session']['intent'] ?? null) !== ($snapshot['intent'] ?? null)
				|| ($body['merchantDetails']['merchantId'] ?? null) !== ($scope['merchant_id'] ?? null)
				|| ($body['merchantDetails']['terminalId'] ?? null) !== ($scope['terminal_id'] ?? null)
				|| (time() - (int) ($snapshot['created_at'] ?? 0)) > 1800
				|| ($snapshot['currency'] ?? null) !== $order->getOrderCurrencyCode()
				|| ($body['approvedAmount']['currency'] ?? null) !== ($snapshot['currency'] ?? null)
				|| !isset($body['approvedAmount']['total'], $snapshot['amount'])
				|| abs((float) $body['approvedAmount']['total'] - (float) $snapshot['amount']) > 0.0001
				|| abs((float) $order->getGrandTotal() - (float) $snapshot['amount']) > 0.0001) {
				$this->notSaved($payment, 'session_or_response_validation_failed');
				return;
			}
			$tokens = $this->extractor->extract($body);
			if (!$tokens) {
				$this->notSaved($payment, 'no_usable_payment_tokens');
				return;
			}
			$saved = $this->storage->saveFirst($customerId, $scope, $tokens);
			$payment->setAdditionalInformation('cashapp_tokenization_status', $saved ? 'saved' : 'already_saved');
			$this->logOutcome($payment, $saved ? 'saved' : 'already_saved');
		} catch (\Throwable $exception) {
			// No exception details, tokens or response payloads in logs or shopper messages.
			$this->notSaved($payment, 'persistence_exception');
		}
	}

	private function logOutcome($payment, string $reason): void
	{
		try {
			$this->logger->logInfo(1, 'Cash App token persistence: ' . $reason,
				'Order ID: ' . (string) $payment->getOrder()->getIncrementId());
		} catch (\Throwable $exception) {
			// Diagnostic logging must not interrupt payment or token persistence.
		}
	}

	private function notSaved($payment, string $reason): void
	{
		try {
			$payment->setAdditionalInformation('cashapp_tokenization_status', 'not_saved');
			$this->logger->logError(1, 'Cash App token persistence failed: ' . $reason,
				'Order ID: ' . (string) $payment->getOrder()->getIncrementId());
			$this->messages->addNoticeMessage(__('Your payment was completed, but Cash App Pay could not be saved for later use.'));
		} catch (\Throwable $exception) {
			// Reporting is best-effort: an approved payment must never be retried because saving failed.
		}
	}
}

