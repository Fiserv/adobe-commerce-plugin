<?php
namespace Fiserv\Payments\Model\CashApp;

use Fiserv\Payments\Gateway\Config\CashApp\Config;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class SessionPolicy
{
	public const SNAPSHOT = 'cashapp_save_session';
	private $checkout;
	private $customer;
	private $config;
	private $commerceHub;
	private $storage;
	private $quotes;
	private $stores;
	private $context;
	private $canSave = false;

	public function __construct(
		CheckoutSession $checkout,
		CustomerSession $customer,
		Config $config,
		CommerceHubConfig $commerceHub,
		TokenStorage $storage,
		CartRepositoryInterface $quotes,
		StoreManagerInterface $stores
	) {
		$this->checkout = $checkout;
		$this->customer = $customer;
		$this->config = $config;
		$this->commerceHub = $commerceHub;
		$this->storage = $storage;
		$this->quotes = $quotes;
		$this->stores = $stores;
	}

	public function prepare(array $data): void
	{
		$this->clear();
		if (($data['paymentCode'] ?? '') !== Config::CODE) {
			return;
		}
		$quote = $this->checkout->getQuote();
		$customerId = (int) $this->customer->getCustomerId();
		$storeId = (int) $quote->getStoreId();
		$owned = $this->customer->isLoggedIn() && $customerId > 0
			&& (int) $quote->getCustomerId() === $customerId && $quote->getIsActive();
		$this->canSave = $owned && $this->config->isActive($storeId)
			&& $this->config->isTokenizationEnabled($storeId) && !$this->storage->hasForCustomer($customerId);
		if (!$quote->getId()) {
			throw new LocalizedException(__('Your checkout session is no longer available.'));
		}
		// Only server-written routing state is persisted; browser additional_data cannot set it.
		$quote->getPayment()->unsAdditionalInformation(self::SNAPSHOT);
		$this->quotes->save($quote);
		$action = $this->config->getPaymentAction($storeId);
		$intent = in_array($action, ['AUTH', 'AUTHORIZE', 'authorize'], true) ? 'AUTHORIZE' : null;
		if (in_array($action, ['SALE', 'CAPTURE', 'authorize_capture'], true)) {
			$intent = 'CAPTURE';
		}
		$scope = [
			'website_id' => (int) $this->stores->getStore($storeId)->getWebsiteId(),
			'merchant_id' => (string) $this->commerceHub->getMerchantId($storeId),
			'terminal_id' => (string) $this->commerceHub->getTerminalId($storeId),
			'environment' => (string) $this->commerceHub->getApiEnvironment($storeId),
		];
		$this->context = [
			'can_save' => $this->canSave,
			'customer_id' => $customerId,
			'quote_id' => (int) $quote->getId(),
			'scope' => $scope,
			'amount' => (float) $quote->getGrandTotal(),
			'currency' => (string) $quote->getQuoteCurrencyCode(),
			'intent' => $intent,
		];
		if ($intent === null || $this->context['amount'] <= 0 || $this->context['currency'] !== 'USD'
			|| !$scope['merchant_id'] || !$scope['terminal_id'] || !$scope['environment']) {
			$this->canSave = false;
			$this->context['can_save'] = false;
		}
	}

	/** Saving is local response persistence, not a change to gateway session creation. */
	public function contribute(array $data, string $endpoint): array
	{
		return $data;
	}

	public function record(array $credentials): void
	{
		if ($this->context === null) {
			return;
		}
		if (empty($credentials['sessionId']) || !is_string($credentials['sessionId'])) {
			throw new LocalizedException(__('Unable to initialize Cash App saving. Please try again.'));
		}
		$quote = $this->checkout->getQuote();
		$snapshot = $this->context;
		$snapshot['session_id'] = $credentials['sessionId'];
		$snapshot['created_at'] = time();
		$quote->getPayment()->setAdditionalInformation(self::SNAPSHOT, $snapshot);
		$this->quotes->save($quote);
	}

	public function canSave(): bool
	{
		return $this->canSave;
	}

	public function clear(): void
	{
		$this->context = null;
		$this->canSave = false;
	}
}


