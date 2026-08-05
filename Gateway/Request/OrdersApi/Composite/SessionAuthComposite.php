<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\OrdersApi\Composite;

use Fiserv\Payments\Gateway\Request\OrdersApi\Composite\OrderApiCompositeBase;
use Fiserv\Payments\Gateway\Request\OrdersApi\OrderApiTransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\SessionSourceDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Fiserv\Payments\Observer\PayPal\DataAssignObserver;
use Fiserv\Payments\Gateway\Request\CommerceHub\BillingAddressDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\CustomerDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionInteractionDataBuilder;
use Magento\Framework\ObjectManager\TMapFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class SessionAuthComposite
 */
class SessionAuthComposite extends OrderApiCompositeBase
{
	const ENDPOINT = "v1/sessions/completions";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @param MultiLevelLogger $logger
	 * @param TMapFactory $tmapFactory
	 * @param array $builders
	 */
	public function __construct(
		MultiLevelLogger $logger,
		TMapFactory $tmapFactory,
		array $builders = []
	) {
		parent::__construct($tmapFactory, $builders);
		$this->logger = $logger;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$this->logger->logInfo(1, "Initiating AUTH Transaction");

		$result = parent::build($buildSubject);

		$sessionSource = $result[SessionSourceDataBuilder::SESSION_SOURCE_KEY];
        $sessionId = $sessionSource->getSessionId();
        $payload = [
            'session' => ['sessionId' => $sessionId],
            'amount' => $result[AmountDataBuilder::AMOUNT_KEY],
            'transactionDetails' => $result[OrderApiTransactionDetailsDataBuilder::TXN_DETAILS_KEY],
            'transactionInteraction' => $result[TransactionInteractionDataBuilder::TXN_INTERACTION_KEY],
            'merchantDetails' => $result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY],
            'billingAddress' => $result[BillingAddressDataBuilder::BILLING_ADDRESS_KEY],
            'customer' => $result[CustomerDataBuilder::CUSTOMER_KEY]
        ];

		return [
			self::REQUEST_KEY => $payload,
			self::ENDPOINT_KEY => self::ENDPOINT
		];
	}
}
