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
	const ACH_LEGAL_TEXT_ACCEPTED_KEY = 'ach_legal_text_accepted';
	const ACH_LEGAL_TEXT_KEY = 'ach_legal_text';
	const ACH_LEGAL_TEXT_ACCEPTED_AT_KEY = 'ach_legal_text_accepted_at';
	const ACH_ROUTING_NUMBER_KEY = 'routingNumber';
	const ACH_ROUTING_NUMBER_SNAKE_KEY = 'routing_number';

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
		self::ACH_LEGAL_TEXT_ACCEPTED_KEY,
		self::ACH_LEGAL_TEXT_KEY,
		self::ACH_LEGAL_TEXT_ACCEPTED_AT_KEY,
		self::ACH_ROUTING_NUMBER_KEY,
		self::ACH_ROUTING_NUMBER_SNAKE_KEY
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
