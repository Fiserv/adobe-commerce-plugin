<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\OrdersApi\Composite;

use Fiserv\Payments\Gateway\Request\OrdersApi\Composite\OrderApiCompositeBase;
use Fiserv\Payments\Gateway\Request\OrdersApi\SaleDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\SessionSourceDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\BillingAddressDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\CustomerDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionInteractionDataBuilder;
use Magento\Framework\ObjectManager\TMapFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class SessionSaleComposite
 *
 * NOTE: The v1/sessions/completions endpoint is not yet present in the
 * published OpenAPI YAML (as of v1.26.0802), so no auto-generated model
 * class exists for its request body.  An array is used here as an interim
 * measure until Fiserv publishes the schema and a class can be generated.
 */
class SessionSaleComposite extends OrderApiCompositeBase
{
	const ENDPOINT = "v1/sessions/completions";

	/** @var MultiLevelLogger */
	private $logger;

	public function __construct(
		MultiLevelLogger $logger,
		TMapFactory $tmapFactory,
		array $builders = []
	) {
		parent::__construct($tmapFactory, $builders);
		$this->logger = $logger;
	}

	/** @inheritdoc */
	public function build(array $buildSubject)
	{
		$this->logger->logInfo(1, "Initiating Sale Transaction");

		$result = parent::build($buildSubject);

		$txnDetails = $result[SaleDetailsDataBuilder::TXN_DETAILS_KEY];
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

		return [
			self::REQUEST_KEY            => $payload,
			self::ENDPOINT_KEY           => self::ENDPOINT,
			self::ORDER_INCREMENT_ID_KEY => $orderIncrementId,
		];
	}
}
