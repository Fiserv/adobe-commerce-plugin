<?php
namespace Fiserv\Payments\Observer\CashApp;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class DataAssignObserver extends AbstractDataAssignObserver
{
	public function execute(Observer $observer)
	{
		$data = $this->readDataArgument($observer)->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
		$data = is_array($data) ? $data : [];
		$payment = $this->readPaymentModelArgument($observer);
		$extension = $payment->getExtensionAttributes();
		if ($extension && method_exists($extension, 'setVaultPaymentToken')) {
			$extension->setVaultPaymentToken(null);
		}

		$hash = $data['public_hash'] ?? '';
		$session = $data['payment_session'] ?? '';
		$payment->setAdditionalInformation('public_hash', is_string($hash) ? $hash : '');
		$payment->setAdditionalInformation('payment_session', is_string($session) ? $session : '');
		$payment->setAdditionalInformation('is_active_payment_token_enabler',
			empty($hash) && in_array($data['is_active_payment_token_enabler'] ?? false, [true, 1, '1', 'true'], true));

		foreach (['payment_token', 'token_source', 'expiration_month', 'expiration_year', '3DSecureId'] as $key) {
			$payment->unsAdditionalInformation($key);
		}
	}
}
