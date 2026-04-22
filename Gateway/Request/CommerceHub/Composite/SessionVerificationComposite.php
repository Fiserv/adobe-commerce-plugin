<?php

namespace Fiserv\Payments\Gateway\Request\CommerceHub\Composite;

use Fiserv\Payments\Gateway\Request\CommerceHub\BillingAddressDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\SessionSourceDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\ObjectManager\TMapFactory;

/**
 * Builds account verification payload for payment-session based checkout.
 */
class SessionVerificationComposite extends ChCompositeBase
{
	const ENDPOINT = 'payments-vas/v1/accounts/verification';
	const SOURCE_KEY = 'source';

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
		$this->logger->logInfo(1, 'Initiating account verification request');
		$result = parent::build($buildSubject);

		$request = [
			self::SOURCE_KEY => $result[SessionSourceDataBuilder::SESSION_SOURCE_KEY],
			MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY => $result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY],
			BillingAddressDataBuilder::BILLING_ADDRESS_KEY => $result[BillingAddressDataBuilder::BILLING_ADDRESS_KEY]
		];

		return [
			self::REQUEST_KEY => $request,
			self::ENDPOINT_KEY => self::ENDPOINT
		];
	}
}

