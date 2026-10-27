<?php
namespace Fiserv\Payments\Model\CashApp;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;

/** Server-owned consent binding for SDK sessions; never trust client-supplied amounts or customer IDs. */
class SessionContext
{
	public const LIFETIME = 1800;
	private $checkoutSession;
	private $customerSession;
	private $formKey;
	private $tokenManagement;

	public function __construct(
		CheckoutSession $checkoutSession,
		CustomerSession $customerSession,
		FormKey $formKey,
		TokenManagement $tokenManagement
	) {
		$this->checkoutSession = $checkoutSession;
		$this->customerSession = $customerSession;
		$this->formKey = $formKey;
		$this->tokenManagement = $tokenManagement;
	}

	public function prepare(array $data): array
	{
		$quote = $this->checkoutSession->getQuote();
		$customerId = $this->customerSession->isLoggedIn() ? (int) $this->customerSession->getCustomerId() : 0;
		if (!$quote->getId() || !$quote->getIsActive() || (int) $quote->getCustomerId() !== $customerId ||
			!hash_equals($this->formKey->getFormKey(), (string) ($data['form_key'] ?? ''))) {
			throw new LocalizedException(__('Please refresh checkout before using Cash App Pay.'));
		}

		$data['cashapp_save_payment_method'] = $customerId > 0 &&
			$this->tokenManagement->getOriginal($customerId) === null &&
			in_array($data['cashapp_save_payment_method'] ?? false, [true, 1, '1', 'true'], true);
		$data['isTokenization'] = false;
		$data['amount'] = [
			'total' => round((float) $quote->getBaseGrandTotal(), 2, PHP_ROUND_HALF_UP),
			'currency' => $quote->getBaseCurrencyCode(),
		];

		return [$data, [
			'customer_id' => $customerId,
			'quote_id' => (int) $quote->getId(),
			'save' => $data['cashapp_save_payment_method'],
			'amount' => $data['amount'],
			'created_at' => time(),
		]];
	}

	public function remember(array $credentials, array $context): void
	{
		$sessionId = $credentials['sessionId'] ?? null;
		if (!is_string($sessionId) || $sessionId === '') {
			throw new LocalizedException(__('Cash App Pay did not return a session.'));
		}
		$sessions = array_filter($this->checkoutSession->getCashappSaveSessions() ?: [], function ($item) {
			return is_array($item) && time() - ($item['created_at'] ?? 0) < self::LIFETIME;
		});
		$sessions[$sessionId] = $context;
		$this->checkoutSession->setCashappSaveSessions(array_slice($sessions, -10, null, true));
	}

	public function get(string $sessionId): ?array
	{
		$context = ($this->checkoutSession->getCashappSaveSessions() ?: [])[$sessionId] ?? null;
		return is_array($context) && time() - ($context['created_at'] ?? 0) < self::LIFETIME ? $context : null;
	}
}
