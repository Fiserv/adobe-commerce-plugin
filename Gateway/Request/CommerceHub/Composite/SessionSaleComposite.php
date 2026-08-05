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
 */
class SessionSaleComposite extends ChCompositeBase
{
	const ENDPOINT = "v1/sessions/completions";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	private $chConfig;

	/**
	 * @param MultiLevelLogger $logger
	 * @param TMapFactory $tmapFactory
	 * @param array $builders
	 */
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

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$this->logger->logInfo(1, "Initiating Sale Transaction");

		$result = parent::build($buildSubject);
		$sessionSource = $result[SessionSourceDataBuilder::SESSION_SOURCE_KEY];
		$sessionId = $sessionSource->getSessionId();

		$payload = [
            'session' => ['sessionId' => $sessionId],
            'amount' => $result[AmountDataBuilder::AMOUNT_KEY],
            'transactionDetails' => $result[TransactionDetailsDataBuilder::TXN_DETAILS_KEY],
            'transactionInteraction' => $result[TransactionInteractionDataBuilder::TXN_INTERACTION_KEY],
            'merchantDetails' => $result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY],
            'billingAddress' => $result[BillingAddressDataBuilder::BILLING_ADDRESS_KEY],
            'customer' => $result[CustomerDataBuilder::CUSTOMER_KEY]
        ];


        // 3D Secure can be enabled but not run (e.g. admin panel order creation)
		if ($this->chConfig->isThreeDSEnabled() && isset($result[ThreeDSecureDataBuilder::KEY_3DS_DATA]))
		{
			$payload['additionalData3DS'] = $result[ThreeDSecureDataBuilder::KEY_3DS_DATA];
		}

		return [
			self::REQUEST_KEY => $payload,
			self::ENDPOINT_KEY => self::ENDPOINT
		];
	}
}
