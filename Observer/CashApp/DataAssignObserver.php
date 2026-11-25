<?php
namespace Fiserv\Payments\Observer\CashApp;

use Fiserv\Payments\Gateway\Config\CashApp\Config;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Copies Cash App checkout data onto the payment.
 * fiserv_cashapp: SDK session flow (session id, save consent). fiserv_cashapp_vault: saved token, public hash only
 * (the real token is resolved server-side). Also registered on the generic vault event, so other methods are ignored.
 */
class DataAssignObserver extends AbstractDataAssignObserver
{
	public function execute(Observer $observer)
	{
		$payment = $this->readPaymentModelArgument($observer);
		$method = $payment->getMethod();
		if ($method !== Config::CODE && $method !== Config::VAULT_CODE) {
			return;
		}

		$data = $this->readDataArgument($observer)->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
		$payment->setAdditionalInformation('public_hash', $data['public_hash'] ?? '');
		if ($method === Config::VAULT_CODE) {
			return;
		}

		$extension = $payment->getExtensionAttributes();
		if ($extension && method_exists($extension, 'setVaultPaymentToken')) {
			$extension->setVaultPaymentToken(null);
		}
		$payment->setAdditionalInformation('payment_session', $data['payment_session'] ?? '');
		$payment->setAdditionalInformation('is_active_payment_token_enabler', $data['is_active_payment_token_enabler'] ?? false);
	}
}
