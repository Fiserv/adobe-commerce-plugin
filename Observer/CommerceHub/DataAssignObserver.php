<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Observer\CommerceHub;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Class DataAssignObserver
 */
class DataAssignObserver extends AbstractDataAssignObserver
{
	const SESSION_ID_KEY = 'payment_session';
	const PAYMENT_TOKEN_KEY = 'payment_token';
	const PAYMENT_TYPE_KEY = 'payment_type';
	const TOKEN_SOURCE_KEY = 'token_source';
	const EXP_MONTH_KEY = 'expiration_month';
	const EXP_YEAR_KEY = 'expiration_year';
	const NAME_ON_CARD_KEY = 'nameOnCard';
	const THREE_D_SECURE_KEY = '3DSecureId';
	const IS_SUBSCRIPTION_KEY = 'is_subscription';
	const SUBSCRIPTION_INTERVAL_VALUE_KEY = 'subscription_interval_value';
	const SUBSCRIPTION_INTERVAL_UNIT_KEY = 'subscription_interval_unit';
	const SCHEME_REFERENCE_TRANSACTION_ID_KEY = 'scheme_reference_transaction_id';

	/**
	 * @var array
	 */
	protected $additionalInformationList = [
		self::SESSION_ID_KEY,
		self::PAYMENT_TYPE_KEY,
		self::PAYMENT_TOKEN_KEY,
		self::TOKEN_SOURCE_KEY,
		self::EXP_MONTH_KEY,
		self::EXP_YEAR_KEY,
		self::THREE_D_SECURE_KEY,
		self::IS_SUBSCRIPTION_KEY,
		self::SUBSCRIPTION_INTERVAL_VALUE_KEY,
		self::SUBSCRIPTION_INTERVAL_UNIT_KEY,
		self::SCHEME_REFERENCE_TRANSACTION_ID_KEY
	];

	/**
	 * @param Observer $observer
	 * @return void
	 */
	public function execute(Observer $observer)
	{
		$data = $this->readDataArgument($observer);

		$additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

		if (!is_array($additionalData)) {
			return;
		}

		$paymentInfo = $this->readPaymentModelArgument($observer);

		foreach ($this->additionalInformationList as $additionalInformationKey) {
			if (isset($additionalData[$additionalInformationKey])) {
				$paymentInfo->setAdditionalInformation(
					$additionalInformationKey,
					$additionalData[$additionalInformationKey]
				);
			}
		}
	}
}
