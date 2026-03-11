<?php

namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Fiserv\Payments\Gateway\Config\PayPal\Config as PayPalConfig;
use Fiserv\Payments\Gateway\Config\Venmo\Config as VenmoConfig;
use Fiserv\Payments\Gateway\Config\Affirm\Config as AffirmConfig;
use Fiserv\Payments\Gateway\Config\ApplePay\Config as ApplePayConfig;
use Fiserv\Payments\Gateway\Config\SamsungPay\Config as SamsungPayConfig;
use Fiserv\Payments\Gateway\Config\PayPal\Fastlane\Config as FastlaneConfig;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider;
use Fiserv\Payments\Model\Config\PayPal\ConfigProvider as PayPalConfigProvider;
use Fiserv\Payments\Model\Config\Venmo\ConfigProvider as VenmoConfigProvider;
use Fiserv\Payments\Model\Config\Affirm\ConfigProvider as AffirmConfigProvider;
use Fiserv\Payments\Model\Config\ApplePay\ConfigProvider as ApplePayConfigProvider;
use Fiserv\Payments\Model\Config\SamsungPay\ConfigProvider as SamsungPayConfigProvider;
use Fiserv\Payments\Model\Config\PayPal\Fastlane\ConfigProvider as FastlaneConfigProvider;
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Payment\Model\Method\Adapter;

/**
 * Plugin that controls availability of all Fiserv payment methods.
 *
 * When a payment method is disabled by the admin mid-transaction (i.e. the
 * customer already selected and confirmed the method on their current quote),
 * the transaction is allowed to complete rather than failing abruptly.
 * New checkout attempts with that method will be blocked as expected.
 */
class PaymentMethodAvailable
{
	/** @var MultiLevelLogger */
	protected $logger;

	/** @var CommerceHubConfig */
	private $commerceHubConfig;

	/** @var PayPalConfig */
	private $paypalConfig;

	/** @var VenmoConfig */
	private $venmoConfig;

	/** @var AffirmConfig */
	private $affirmConfig;

	/** @var ApplePayConfig */
	private $applePayConfig;

	/** @var SamsungPayConfig */
	private $samsungPayConfig;

	/** @var FastlaneConfig */
	private $fastlaneConfig;

	/** @var CheckoutSession */
	private $checkoutSession;

	public function __construct(
		MultiLevelLogger $logger,
		CommerceHubConfig $commerceHubConfig,
		PayPalConfig $paypalConfig,
		VenmoConfig $venmoConfig,
		AffirmConfig $affirmConfig,
		ApplePayConfig $applePayConfig,
		SamsungPayConfig $samsungPayConfig,
		FastlaneConfig $fastlaneConfig,
		CheckoutSession $checkoutSession
	) {
		$this->logger              = $logger;
		$this->commerceHubConfig   = $commerceHubConfig;
		$this->paypalConfig        = $paypalConfig;
		$this->venmoConfig         = $venmoConfig;
		$this->affirmConfig        = $affirmConfig;
		$this->applePayConfig      = $applePayConfig;
		$this->samsungPayConfig    = $samsungPayConfig;
		$this->fastlaneConfig      = $fastlaneConfig;
		$this->checkoutSession     = $checkoutSession;
	}

	/**
	 * After-plugin on Adapter::isAvailable().
	 *
	 * Returns false for disabled Fiserv methods UNLESS the customer is already
	 * mid-transaction with that method (quote payment already set), in which
	 * case we allow the in-flight transaction to complete naturally.
	 */
	public function afterIsAvailable(Adapter $subject, $result)
	{
		$methodCode = $subject->getCode();

		// Only handle Fiserv payment methods
		if (!$this->isFiservMethod($methodCode)) {
			return $result;
		}

		$isEnabled = $this->isMethodEnabled($methodCode);

		// Method is active — return the standard Magento result
		if ($isEnabled) {
			return $result;
		}

		// Method is disabled via admin config.
		// Allow the transaction to complete if the customer already selected
		// this method on their quote (mid-transaction scenario).
		try {
			$quote = $this->checkoutSession->getQuote();
			if ($quote && $quote->getId()) {
				$quotePaymentMethod = $quote->getPayment()->getMethod();
				if ($quotePaymentMethod === $methodCode) {
					$this->logger->logInfo(
						1,
						sprintf(
							'Payment method "%s" is disabled but allowing in-flight transaction for quote #%s.',
							$methodCode,
							$quote->getId()
						)
					);
					return true;
				}
			}
		} catch (\Exception $e) {
			$this->logger->logError(
				1,
				'PaymentMethodAvailable: unable to read checkout session — ' . $e->getMessage()
			);
		}

		$this->logger->logInfo(
			1,
			sprintf('Payment method "%s" is disabled. Blocking new checkout attempt.', $methodCode)
		);

		return false;
	}

	/**
	 * Returns true if the given code belongs to a Fiserv payment method
	 * handled by this plugin.
	 */
	private function isFiservMethod(string $methodCode): bool
	{
		return in_array($methodCode, $this->getHandledMethodCodes(), true);
	}

	/**
	 * Returns the active status from the method-specific gateway config.
	 * CommerceHub requires both the module toggle AND the payment toggle to be on.
	 */
	private function isMethodEnabled(string $methodCode): bool
	{
		switch ($methodCode) {
			case ConfigProvider::CODE:
				// fiserv_commercehub: requires module active + payment active
				return $this->commerceHubConfig->isActive() && $this->commerceHubConfig->isPaymentActive();

			case PayPalConfigProvider::CODE:
				return $this->paypalConfig->isActive();

			case VenmoConfigProvider::CODE:
				return $this->venmoConfig->isActive();

			case AffirmConfigProvider::CODE:
				return $this->affirmConfig->isActive();

			case ApplePayConfigProvider::CODE:
				return $this->applePayConfig->isActive();

			case SamsungPayConfigProvider::CODE:
				return $this->samsungPayConfig->isActive();

			case FastlaneConfigProvider::CODE:
				return $this->fastlaneConfig->isActive();

			default:
				return true;
		}
	}

	/**
	 * All Fiserv payment method codes managed by this plugin.
	 */
	private function getHandledMethodCodes(): array
	{
		return [
			ConfigProvider::CODE,            // fiserv_commercehub
			PayPalConfigProvider::CODE,      // fiserv_paypal
			VenmoConfigProvider::CODE,       // fiserv_venmo
			AffirmConfigProvider::CODE,      // fiserv_affirm
			ApplePayConfigProvider::CODE,    // fiserv_applepay
			SamsungPayConfigProvider::CODE,  // fiserv_samsung_pay
			FastlaneConfigProvider::CODE,    // fiserv_paypal_fastlane
		];
	}
}
