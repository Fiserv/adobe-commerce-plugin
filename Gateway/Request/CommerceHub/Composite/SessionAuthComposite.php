<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub\Composite;

use Fiserv\Payments\Gateway\Request\CommerceHub\Composite\ChCompositeBase;
use Fiserv\Payments\Lib\CommerceHub\Model\ChargesRequest;
use Fiserv\Payments\Gateway\Request\CommerceHub\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\SessionSourceDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionInteractionDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\BillingAddressDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\ThreeDSecureDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\CustomerDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\StoredCredentialsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\AdditionalDataCommonDataBuilder;
use Magento\Framework\ObjectManager\TMapFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;

/**
 * Class SessionAuthComposite
 */
class SessionAuthComposite extends ChCompositeBase
{
	public const ENDPOINT = "payments/v1/charges";

	public function __construct(
		private readonly MultiLevelLogger $logger,
		TMapFactory $tmapFactory,
		private readonly Config $chConfig,
		array $builders = []
	) {
		parent::__construct($tmapFactory, $builders);
	}
	
	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject): array
	{
		$result = parent::build($buildSubject);
		$orderIncrementId = $result[TransactionDetailsDataBuilder::TXN_DETAILS_KEY]['merchant_order_id'] ?? null;
		if ($orderIncrementId !== null) {
			$this->logger->logInfo(1, "Initiating Auth Transaction", "Order ID:" . $orderIncrementId);
		} else {
			$this->logger->logInfo(1, "Initiating Auth Transaction");
		}

		$req = new ChargesRequest();
		$req->setAmount($result[AmountDataBuilder::AMOUNT_KEY]);
		$req->setSource($result[SessionSourceDataBuilder::SESSION_SOURCE_KEY]);
		$req->setTransactionDetails($result[TransactionDetailsDataBuilder::TXN_DETAILS_KEY]);
		$req->setTransactionInteraction($result[TransactionInteractionDataBuilder::TXN_INTERACTION_KEY]);
		$req->setMerchantDetails($result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY]);
		$req->setBillingAddress($result[BillingAddressDataBuilder::BILLING_ADDRESS_KEY]);
		$req->setCustomer($result[CustomerDataBuilder::CUSTOMER_KEY]);

		// 3D Secure can be enabled but not run (e.g. admin panel order creation)
		if ($this->chConfig->isThreeDSEnabled() && isset($result[ThreeDSecureDataBuilder::KEY_3DS_DATA]))
		{
			$req->setAdditionalData3Ds($result[ThreeDSecureDataBuilder::KEY_3DS_DATA]);
		}

		// Subscription-specific fields (only added when StoredCredentialsDataBuilder returns data)
		if (isset($result[StoredCredentialsDataBuilder::STORED_CREDENTIALS_KEY])) {
			$req->setStoredCredentials($result[StoredCredentialsDataBuilder::STORED_CREDENTIALS_KEY]);
		}

		if (isset($result[AdditionalDataCommonDataBuilder::ADDITIONAL_DATA_COMMON_KEY])) {
			$req->setAdditionalDataCommon($result[AdditionalDataCommonDataBuilder::ADDITIONAL_DATA_COMMON_KEY]);
		}

		return [
			self::REQUEST_KEY => $req,
			self::ENDPOINT_KEY => self::ENDPOINT
		];
	}
}
