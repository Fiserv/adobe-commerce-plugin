<?php
namespace Fiserv\Payments\Gateway\Request\CommerceHub\Composite;

use Fiserv\Payments\Gateway\Request\CommerceHub\Composite\ChCompositeBase;
use Fiserv\Payments\Gateway\Request\CommerceHub\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TokenSourceDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionInteractionDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\BillingAddressDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\CustomerDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\StoredCredentialsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\ThreeDSecureDataBuilder;
use Magento\Framework\ObjectManager\TMapFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;

class SessionTokenAuthComposite extends ChCompositeBase
{
	const ENDPOINT = "v1/sessions/completions";

	private $logger;
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

	public function build(array $buildSubject)
	{
		$result = parent::build($buildSubject);

		$txnDetails = $result[TransactionDetailsDataBuilder::TXN_DETAILS_KEY];
		$orderIncrementId = $txnDetails->getMerchantOrderId() ?? "";
		$this->logger->logInfo(1, "Initiating Token Auth Transaction (Sessions API)", "Order ID: $orderIncrementId");

		$payload = [
			'source' => $result[TokenSourceDataBuilder::TOKEN_SOURCE_KEY],
			'amount' => $result[AmountDataBuilder::AMOUNT_KEY],
			'transactionDetails' => $txnDetails,
			'transactionInteraction' => $result[TransactionInteractionDataBuilder::TXN_INTERACTION_KEY],
			'merchantDetails' => $result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY],
			'billingAddress' => $result[BillingAddressDataBuilder::BILLING_ADDRESS_KEY],
			'customer' => $result[CustomerDataBuilder::CUSTOMER_KEY],
		];

		if (isset($result[StoredCredentialsDataBuilder::STORED_CREDENTIALS_KEY])) {
			$payload['storedCredentials'] = $result[StoredCredentialsDataBuilder::STORED_CREDENTIALS_KEY];
		}

		// 3D Secure can be enabled but not run (e.g. admin panel order creation)
		if ($this->chConfig->isThreeDSEnabled() && isset($result[ThreeDSecureDataBuilder::KEY_3DS_DATA]))
		{
			$payload['additionalData3DS'] = $result[ThreeDSecureDataBuilder::KEY_3DS_DATA];
		}

		return [
			self::REQUEST_KEY => $payload,
			self::ENDPOINT_KEY => self::ENDPOINT,
			self::ORDER_INCREMENT_ID_KEY => $orderIncrementId,
		];
	}
}

