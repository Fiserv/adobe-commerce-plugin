<?php
declare(strict_types=1);
namespace Fiserv\Payments\Service;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order\AddressFactory as OrderAddressFactory;
use Magento\Sales\Model\Order\PaymentFactory as OrderPaymentFactory;
use Magento\Sales\Model\OrderFactory;
use Throwable;

/**
 * Builds an in-memory Sales Order + Order Payment context from a Quote.
 * This is used to satisfy Magento gateway adapters that expect an order-backed payment context.
 */
class OrderPaymentContextBuilder
{
	public function __construct(
		private readonly OrderFactory $orderFactory,
		private readonly OrderPaymentFactory $orderPaymentFactory,
		private readonly OrderAddressFactory $orderAddressFactory
	) {}

	/**
	 * @param Quote $quote
	 * @param mixed $quotePayment Quote payment object (Magento\Quote\Model\Quote\Payment)
	 * @param string|null $merchantOrderId Optional increment/merchant order id to place on the order context
	 * @return mixed Magento\Sales\Model\Order\Payment
	 */
	public function createOrderPaymentFromQuote(Quote $quote, $quotePayment, ?string $merchantOrderId = null)
	{
		$order = $this->orderFactory->create();

		$this->applyIdentity($order, $quote, $merchantOrderId);
		$this->applyCurrency($order, $quote);
		$this->applyAddresses($order, $quote);

		$orderPayment = $this->orderPaymentFactory->create();
		$this->applyPayment($orderPayment, $order, $quotePayment);

		return $orderPayment;
	}

	private function applyIdentity($order, Quote $quote, ?string $merchantOrderId): void
	{
		try {
			if ($merchantOrderId) {
				$order->setIncrementId($merchantOrderId);
			} elseif ($quote->getReservedOrderId()) {
				$order->setIncrementId($quote->getReservedOrderId());
			}

			$order->setStoreId($quote->getStoreId());

			if ($quote->getCustomerId()) {
				$order->setCustomerId($quote->getCustomerId());
			}
			if ($quote->getCustomerEmail()) {
				$order->setCustomerEmail($quote->getCustomerEmail());
			}
			if (method_exists($quote, 'getCustomerFirstname') && $quote->getCustomerFirstname()) {
				$order->setCustomerFirstname($quote->getCustomerFirstname());
			}
			if (method_exists($quote, 'getCustomerLastname') && $quote->getCustomerLastname()) {
				$order->setCustomerLastname($quote->getCustomerLastname());
			}
		} catch (Throwable) {
			// ignore
		}
	}

	private function applyCurrency($order, Quote $quote): void
	{
		$quoteCurrency = null;
		$baseCurrency = null;

		try {
			if (method_exists($quote, 'getQuoteCurrencyCode') && $quote->getQuoteCurrencyCode()) {
				$quoteCurrency = $quote->getQuoteCurrencyCode();
			}
			if (method_exists($quote, 'getBaseCurrencyCode') && $quote->getBaseCurrencyCode()) {
				$baseCurrency = $quote->getBaseCurrencyCode();
			}
		} catch (Throwable) {
			// ignore
		}

		if (!$quoteCurrency) {
			try {
				$currencyObj = $quote->getCurrency();
				if ($currencyObj) {
					if (method_exists($currencyObj, 'getQuoteCurrencyCode') && $currencyObj->getQuoteCurrencyCode()) {
						$quoteCurrency = $currencyObj->getQuoteCurrencyCode();
					} elseif (method_exists($currencyObj, 'getCurrencyCode') && $currencyObj->getCurrencyCode()) {
						$quoteCurrency = $currencyObj->getCurrencyCode();
					}
				}
			} catch (Throwable) {
				// ignore
			}
		}

		$quoteCurrency = $quoteCurrency ?: 'USD';
		$baseCurrency = $baseCurrency ?: $quoteCurrency;

		try {
			$order->setOrderCurrencyCode((string)$quoteCurrency);
			$order->setGlobalCurrencyCode((string)$quoteCurrency);
			$order->setStoreCurrencyCode((string)$quoteCurrency);
			$order->setBaseCurrencyCode((string)$baseCurrency);

			// Some adapters read "currency_code"
			if (method_exists($order, 'setCurrencyCode')) {
				$order->setCurrencyCode((string)$quoteCurrency);
			}
		} catch (Throwable) {
			// ignore
		}
	}

	private function applyAddresses($order, Quote $quote): void
	{
		try {
			$quoteBilling = method_exists($quote, 'getBillingAddress') ? $quote->getBillingAddress() : null;
			if ($quoteBilling) {
				$billing = $this->orderAddressFactory->create();

				$billing->setFirstname($quoteBilling->getFirstname() ?: (method_exists($quote, 'getCustomerFirstname') ? $quote->getCustomerFirstname() : null));
				$billing->setLastname($quoteBilling->getLastname() ?: (method_exists($quote, 'getCustomerLastname') ? $quote->getCustomerLastname() : null));
				$billing->setCompany($quoteBilling->getCompany());

				$street = $quoteBilling->getStreet();
				$billing->setStreet(is_array($street) ? $street : ($street ? [$street] : []));

				$billing->setCity($quoteBilling->getCity());
				$billing->setRegion($quoteBilling->getRegion());
				$billing->setRegionId($quoteBilling->getRegionId());
				$billing->setPostcode($quoteBilling->getPostcode());
				$billing->setCountryId($quoteBilling->getCountryId());
				$billing->setTelephone($quoteBilling->getTelephone());
				$billing->setEmail($quoteBilling->getEmail() ?: $quote->getCustomerEmail());
				$billing->setCustomerId($quote->getCustomerId() ?: null);

				$order->setBillingAddress($billing);
			}

			$quoteShipping = method_exists($quote, 'getShippingAddress') ? $quote->getShippingAddress() : null;
			if ($quoteShipping && $quoteShipping->getCountryId()) {
				$shipping = $this->orderAddressFactory->create();

				$shipping->setFirstname($quoteShipping->getFirstname() ?: (method_exists($quote, 'getCustomerFirstname') ? $quote->getCustomerFirstname() : null));
				$shipping->setLastname($quoteShipping->getLastname() ?: (method_exists($quote, 'getCustomerLastname') ? $quote->getCustomerLastname() : null));
				$shipping->setCompany($quoteShipping->getCompany());

				$streetS = $quoteShipping->getStreet();
				$shipping->setStreet(is_array($streetS) ? $streetS : ($streetS ? [$streetS] : []));

				$shipping->setCity($quoteShipping->getCity());
				$shipping->setRegion($quoteShipping->getRegion());
				$shipping->setRegionId($quoteShipping->getRegionId());
				$shipping->setPostcode($quoteShipping->getPostcode());
				$shipping->setCountryId($quoteShipping->getCountryId());
				$shipping->setTelephone($quoteShipping->getTelephone());
				$shipping->setEmail($quoteShipping->getEmail() ?: $quote->getCustomerEmail());
				$shipping->setCustomerId($quote->getCustomerId() ?: null);

				$order->setShippingAddress($shipping);
			}
		} catch (Throwable) {
			// ignore
		}
	}

	private function applyPayment($orderPayment, $order, $quotePayment): void
	{
		try {
			$orderPayment->setMethod($quotePayment->getMethod());

			if (method_exists($quotePayment, 'getAdditionalInformation')) {
				$info = $quotePayment->getAdditionalInformation();
				if (is_array($info)) {
					foreach ($info as $key => $value) {
						$orderPayment->setAdditionalInformation($key, $value);
					}
				}
			}

			try {
				$extensionAttributes = $quotePayment->getExtensionAttributes();
				if ($extensionAttributes) {
					$orderPayment->setExtensionAttributes($extensionAttributes);
				}
			} catch (Throwable) {
				// ignore
			}
		} catch (Throwable) {
			// ignore
		}

		try {
			$orderPayment->setOrder($order);
			$order->setPayment($orderPayment);
		} catch (Throwable) {
			// ignore
		}
	}
}