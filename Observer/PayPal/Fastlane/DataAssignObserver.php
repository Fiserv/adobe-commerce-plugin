<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Observer\PayPal\Fastlane;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Class DataAssignObserver
 */
class DataAssignObserver extends AbstractDataAssignObserver
{
	const FASTLANE_CUSTOMER_KEY = 'fastlane_customer';
	const FASTLANE_CARD_ID = 'fastlane_card_id';
	const FASTLANE_SESSION_ID = 'fastlane_session_id';

	/**
	 * @var array
	 */
	protected $additionalInformationList = [
		self::FASTLANE_CUSTOMER_KEY,
		self::FASTLANE_CARD_ID,
		self::FASTLANE_SESSION_ID
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
