<?php
namespace Fiserv\Payments\Gateway\Request\CommerceHub\Composite;

use Fiserv\Payments\Gateway\Request\CommerceHub\Composite\ChCompositeBase;
use Fiserv\Payments\Lib\CommerceHub\Model\ChargesRequest;
use Fiserv\Payments\Gateway\Request\CommerceHub\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TokenSourceDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionInteractionDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\BillingAddressDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\CustomerDataBuilder;
use Magento\Framework\ObjectManager\TMapFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Gateway\Request\CommerceHub\ThreeDSecureDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\StoredCredentialsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\AdditionalDataCommonDataBuilder;

/**
 * Class TokenAuthComposite
 */
class TokenAuthComposite extends ChCompositeBase
{	
	const ENDPOINT = "payments/v1/charges";

	private $logger;
	private $chConfig;
	private $endpoint;

	public function __construct(
		MultiLevelLogger $logger, 
		TMapFactory $tmapFactory, 
		Config $chConfig,
		array $builders = [],
		string $endpoint = self::ENDPOINT)
	{
		parent::__construct($tmapFactory, $builders);
		$this->logger = $logger;
		$this->chConfig = $chConfig;
		$this->endpoint = $endpoint;
	}

	public function build(array $buildSubject)
	{

		$result = parent::build($buildSubject);
		$txnDetails = $result[TransactionDetailsDataBuilder::TXN_DETAILS_KEY];
		$orderIncrementId = $txnDetails->getMerchantOrderId() ?? "";
		$this->logger->logInfo(1, "Initiating Auth Transaction", "Order ID: $orderIncrementId");

		$req = new ChargesRequest();
		$req->setAmount($result[AmountDataBuilder::AMOUNT_KEY]);
		$req->setSource($result[TokenSourceDataBuilder::TOKEN_SOURCE_KEY]);
		$req->setTransactionDetails($txnDetails);
		$req->setTransactionInteraction($result[TransactionInteractionDataBuilder::TXN_INTERACTION_KEY]);
		$req->setMerchantDetails($result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY]);
		$req->setBillingAddress($result[BillingAddressDataBuilder::BILLING_ADDRESS_KEY]);
		$req->setCustomer($result[CustomerDataBuilder::CUSTOMER_KEY]);

		// Stored Credentials required for CIT multi-use token reuse (see StoredCredentialsDataBuilder)
		if (isset($result[StoredCredentialsDataBuilder::STORED_CREDENTIALS_KEY])) {
			$req->setStoredCredentials($result[StoredCredentialsDataBuilder::STORED_CREDENTIALS_KEY]);
		}

		// billPaymentType required on subsequent stored-credential transactions (see AdditionalDataCommonDataBuilder)
		if (isset($result[AdditionalDataCommonDataBuilder::ADDITIONAL_DATA_COMMON_KEY])) {
			$req->setAdditionalDataCommon($result[AdditionalDataCommonDataBuilder::ADDITIONAL_DATA_COMMON_KEY]);
		}

		// 3D Secure can be enabled but not run (e.g. admin panel order creation)
		if ($this->chConfig->isThreeDSEnabled() && isset($result[ThreeDSecureDataBuilder::KEY_3DS_DATA]))
		{
			$req->setAdditionalData3Ds($result[ThreeDSecureDataBuilder::KEY_3DS_DATA]);
		}

		return [ 
			self::REQUEST_KEY => $req,
			self::ENDPOINT_KEY => $this->endpoint,
			self::ORDER_INCREMENT_ID_KEY => $orderIncrementId
		];
	}
}
