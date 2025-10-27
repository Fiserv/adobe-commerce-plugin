<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal\Fastlane\Composite;

use Fiserv\Payments\Gateway\Request\CommerceHub\Composite\ChCompositeBase;
use Fiserv\Payments\Lib\CommerceHub\Model\ChargesRequest;
use Fiserv\Payments\Gateway\Request\CommerceHub\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\PayPal\Fastlane\TokenSourceDataBuilder;
use Fiserv\Payments\Gateway\Request\PayPal\Fastlane\TransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\PayPal\Fastlane\ProviderCredentialsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionInteractionDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\BillingAddressDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\CustomerDataBuilder;
use Magento\Framework\ObjectManager\TMapFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Gateway\Request\CommerceHub\ThreeDSecureDataBuilder;

/**
 * Class TokenSaleComposite
 */
class TokenSaleComposite extends ChCompositeBase
{
	const ENDPOINT = "payments/v1/charges";

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
		array $builders = [])
	{
		parent::__construct($tmapFactory, $builders);
		$this->logger = $logger;
		$this->chConfig = $chConfig;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{

		$result = parent::build($buildSubject);
		$orderIncrementId = $result[TransactionDetailsDataBuilder::TXN_DETAILS_KEY]['merchant_order_id'] ?? null;
		if ($orderIncrementId !== null) {
			$this->logger->logInfo(1, "Initiating Token Sale Transaction", "Order ID:" . $orderIncrementId);
		} else {
			$this->logger->logInfo(1, "Initiating Token Sale Transaction");
		}

		$req = new ChargesRequest();
		$req->setAmount($result[AmountDataBuilder::AMOUNT_KEY]);
		$req->setSource($result[TokenSourceDataBuilder::TOKEN_SOURCE_KEY]);
		$req->setTransactionDetails($result[TransactionDetailsDataBuilder::TXN_DETAILS_KEY]);
		$req->setTransactionInteraction($result[TransactionInteractionDataBuilder::TXN_INTERACTION_KEY]);
		$req->setMerchantDetails($result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY]);
		$req->setBillingAddress($result[BillingAddressDataBuilder::BILLING_ADDRESS_KEY]);
		$req->setCustomer($result[CustomerDataBuilder::CUSTOMER_KEY]);
		$req->setProviderCredentials([$result[ProviderCredentialsDataBuilder::PROVIDER_CREDENTIALS_KEY]]);

		return [
			self::REQUEST_KEY => $req,
			self::ENDPOINT_KEY => self::ENDPOINT
		];
	}
}
