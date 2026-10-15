<?php
namespace Fiserv\Payments\Model\CashApp;

use Fiserv\Payments\Gateway\Config\CashApp\Config;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class SavedPayment
{
	public const CHOICE = 'cashapp_use_saved';
	public const USED = 'cashapp_used_saved_token';
	private $customer;
	private $config;
	private $commerceHub;
	private $storage;
	private $quotes;
	private $stores;

	public function __construct(UserContextInterface $customer, Config $config, CommerceHubConfig $commerceHub,
		TokenStorage $storage, CartRepositoryInterface $quotes, StoreManagerInterface $stores)
	{
		$this->customer = $customer;
		$this->config = $config;
		$this->commerceHub = $commerceHub;
		$this->storage = $storage;
		$this->quotes = $quotes;
		$this->stores = $stores;
	}

	public function resolve($payment): array
	{
		try {
			$order = $payment->getOrder();
			$customerId = (int) $this->customer->getUserId();
			$storeId = (int) $order->getStoreId();
			if ($payment->getMethod() !== Config::CODE
				|| $this->customer->getUserType() !== UserContextInterface::USER_TYPE_CUSTOMER || $customerId <= 0
				|| (int) $order->getCustomerId() !== $customerId || $order->getCustomerIsGuest()
				|| !$this->config->isActive($storeId) || !$this->config->isTokenizationEnabled($storeId)
				|| $order->getOrderCurrencyCode() !== 'USD' || $order->getBaseCurrencyCode() !== 'USD') {
				throw new \UnexpectedValueException();
			}
			$quote = $this->quotes->get((int) $order->getQuoteId());
			if ((int) $quote->getCustomerId() !== $customerId || (int) $quote->getStoreId() !== $storeId) {
				throw new \UnexpectedValueException();
			}
			$token = $this->storage->getReusableToken($customerId, [
				'website_id' => (int) $this->stores->getStore($storeId)->getWebsiteId(),
				'merchant_id' => (string) $this->commerceHub->getMerchantId($storeId),
				'terminal_id' => (string) $this->commerceHub->getTerminalId($storeId),
				'environment' => (string) $this->commerceHub->getApiEnvironment($storeId),
			]);
			if ($token === null) {
				throw new \UnexpectedValueException();
			}
			return $token;
		} catch (\Throwable $exception) {
			throw new LocalizedException(__('Saved Cash App Pay is unavailable. Please use Cash App Pay again or select another payment method.'));
		}
	}
}

