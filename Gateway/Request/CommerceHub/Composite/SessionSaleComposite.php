<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub\Composite;

use Fiserv\Payments\Gateway\Request\CommerceHub\Composite\ChCompositeBase;
use Fiserv\Payments\Gateway\Request\CommerceHub\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\SessionSourceDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\ThreeDSecureDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionInteractionDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\BillingAddressDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\CustomerDataBuilder;
use Magento\Framework\ObjectManager\TMapFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;

/**
 * Class SessionSaleComposite
 *
 * NOTE: The v1/sessions/completions endpoint is not yet present in the
 * published OpenAPI YAML (as of v1.26.0802), so no auto-generated model
 * class exists for its request body.  An array is used here as an interim
 * measure until Fiserv publishes the schema and a class can be generated.
 */
class SessionSaleComposite extends ChCompositeBase
{
	const ENDPOINT = "v1/sessions/completions";

	/** @var MultiLevelLogger */
	private $logger;

	/** @var Config */
	private $chConfig;

	public function __construct(
		MultiLevelLogger $logger,
		TMapFactory $tmapFactory,
		Config $chConfig,
		array $builders = []
	) {
		parent::__construct($tmapFactory, $builders);
		$this->logger = $logger;
		$this->chConfig = $chConfig;
	}

	/** @inheritdoc */
	public function build(array $buildSubject)
	{
		$this->logger->logInfo(1, "Initiating Sale Transaction");

		$result = parent::build($buildSubject);

		$txnDetails = $result[TransactionDetailsDataBuilder::TXN_DETAILS_KEY];
		$orderIncrementId = $txnDetails->getMerchantOrderId() ?? "";

		$payload = [
			'session'              => ['sessionId' => $result[SessionSourceDataBuilder::SESSION_SOURCE_KEY]->getSessionId()],
			'amount'               => $result[AmountDataBuilder::AMOUNT_KEY],
			'transactionDetails'   => $txnDetails,
			'transactionInteraction' => $result[TransactionInteractionDataBuilder::TXN_INTERACTION_KEY],
			'merchantDetails'      => $result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY],
			'billingAddress'       => $result[BillingAddressDataBuilder::BILLING_ADDRESS_KEY],
			'customer'             => $result[CustomerDataBuilder::CUSTOMER_KEY],
		];

		// 3D Secure can be enabled but not run (e.g. admin panel order creation)
		if ($this->chConfig->isThreeDSEnabled() && isset($result[ThreeDSecureDataBuilder::KEY_3DS_DATA]))
		{
			$payload['additionalData3DS'] = $result[ThreeDSecureDataBuilder::KEY_3DS_DATA];
		}

		return [
			self::REQUEST_KEY            => $payload,
			self::ENDPOINT_KEY           => self::ENDPOINT,
			self::ORDER_INCREMENT_ID_KEY => $orderIncrementId,
		];
	}
}
