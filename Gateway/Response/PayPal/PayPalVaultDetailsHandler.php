<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Fiserv\Payments\Gateway\Response\PayPal;

use Fiserv\Payments\Model\Config\PayPal\ConfigProvider;
use Fiserv\Payments\Gateway\Subject\PayPal\SubjectReader;
use Fiserv\Payments\Model\System\Utils\VaultPaymentTokenUtils;
# Removed the import of the missing class TransactionDetailsDataBuilder
use Fiserv\Payments\Model\Source\PayPal\TokenizationStrategy;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Fiserv\Payments\Model\System\Utils\PaymentTokenUtil;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Logger\MultiLevelLogger;


/**
 * Vault Details Handler
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PayPalVaultDetailsHandler implements HandlerInterface
{
	/**
	 * @var Config
	 */
	private $config;
	
	
	protected $vaultPaymentTokenUtils;
	/**
	 * @var PaymentTokenFactoryInterface
	 */
	protected $paymentTokenFactory;

	/**
	 * @var OrderPaymentExtensionInterfaceFactory
	 */
	protected $paymentExtensionFactory;

	/**
	 * @var SubjectReader
	 */
	protected $subjectReader;

	/**
	 * @var Json
	 */
	private $serializer;
	
	/**
	** @var MultiLevelLogger
	**/
	private $logger;

	private $paymentTokenRepository;

	private $paymentTokenManager;
	
	private $encryptor;
	
	/**
	 * PayPalVaultDetailsHandler constructor.
	 *
	 * @param PaymentTokenFactoryInterface $paymentTokenFactory
	 * @param OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
	 * @param SubjectReader $subjectReader
	 * @param Json|null $serializer
     * @param MultiLevelLogger $logger
	 */
	public function __construct(
		Config $config,
		VaultPaymentTokenUtils $vaultPaymentTokenUtils,
		PaymentTokenFactoryInterface $paymentTokenFactory,
		OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
		SubjectReader $subjectReader,
		MultiLevelLogger $logger,
		PaymentTokenRepositoryInterface $paymentTokenRepository,
		PaymentTokenManagementInterface $paymentTokenManager,
		EncryptorInterface $encryptor,
		Json $serializer = null
	) {
		$this->config = $config;
		$this->vaultPaymentTokenUtils = $vaultPaymentTokenUtils;
		$this->paymentTokenFactory = $paymentTokenFactory;
		$this->paymentExtensionFactory = $paymentExtensionFactory;
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
		$this->paymentTokenRepository = $paymentTokenRepository;
		$this->paymentTokenManager = $paymentTokenManager;
		$this->encryptor = $encryptor;
		$this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $handlingSubject, array $response)
	{
		
		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$paypalResponse = $this->subjectReader->readPayPalResponse($response);
		$payment = $paymentDO->getPayment();

		$tokenStrat = $this->config->getTokenStrategy();
		// Replace TransactionDetailsDataBuilder::KEY_CREATE_TOKEN with string literal 'create_token' or appropriate value
		$storeToken = $payment->getStoreVault() === 'create_token' || 
			$tokenStrat === TokenizationStrategy::ALWAYS;

		$visible = $payment->getStoreVault() === 'create_token';

		if ($storeToken && $this->wasTokenRequestSuccessful($paypalResponse)) {
			$existingToken = $this->vaultPaymentTokenUtils->doesTokenExist(
				$paypalResponse["paymentTokens"][0]["tokenData"],
				$payment->getMethodInstance()->getCode(),
				$payment->getOrder()->getCustomerId()
			);

			if ($existingToken === false || (isset($existingToken["is_visible"]) && $existingToken["is_visible"] != $visible)) {
				$paymentToken = $this->getVaultPayPalToken($paypalResponse, $visible);
				$extensionAttributes = $this->getExtensionAttributes($payment);
				$extensionAttributes->setVaultPaymentToken($paymentToken);
			}
		}
	}

	/**
	 * Get vault payment token entity for payment card
	 *
	 * @param array $chResponse
	 * @return PaymentTokenInterface|null
	 */
	public function getVaultPayPalToken($paypalResponse, $visible)
	{
		$paymentToken = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT);
		$tokenArray = $paypalResponse["paymentTokens"][0];

		$details = $this->convertDetailsToJSON([
			"payerEmail" => $paypalResponse["payer"]["email_address"] ?? '',
			"payerId" => $paypalResponse["payer"]["payer_id"] ?? '',
			"tokenSource" => $tokenArray["tokenSource"] ?? '',
			"tokenResponseCode" => $tokenArray["tokenResponseCode"] ?? '',
			"tokenResponseDescription" => $tokenArray["tokenResponseDescription"] ?? '',
		]);
		$paymentToken->setGatewayToken(PaymentTokenUtil::formatTokenDataForPersistence($tokenArray["tokenData"]));
		$paymentToken->setTokenDetails($details);
		$paymentToken->setIsVisible($visible);
		$paymentToken->setIsActive(true);
		$paymentToken->setPaymentMethodCode(ConfigProvider::CODE);
		$paymentToken->setPublicHash($this->generatePublicHash($paymentToken));

		return $paymentToken;
	}

	/**
	 * Convert payment token details to JSON
	 * @param array $details
	 * @return string
	 */
	private function convertDetailsToJSON($details)
	{
		$json = $this->serializer->serialize($details);
		return $json ? $json : '{}';
	}

	/**
	 * Get payment extension attributes
	 * @param InfoInterface $payment
	 * @return OrderPaymentExtensionInterface
	 */
	private function getExtensionAttributes(InfoInterface $payment)
	{
		$extensionAttributes = $payment->getExtensionAttributes();
		if (null === $extensionAttributes) {
			$extensionAttributes = $this->paymentExtensionFactory->create();
			$payment->setExtensionAttributes($extensionAttributes);
		}
		return $extensionAttributes;
	}

	private function wasTokenRequestSuccessful($paypalResponse) {
		return (
			isset($paypalResponse["paymentTokens"]) &&
			$paypalResponse["paymentTokens"][0] !== null &&
			isset($paypalResponse["paymentTokens"][0]["tokenResponseDescription"]) &&
			$paypalResponse["paymentTokens"][0]["tokenResponseDescription"] == "SUCCESS" &&
			isset($paypalResponse["paymentTokens"][0]["tokenData"])
		);
	}
	
	public function generatePublicHash(\Magento\Vault\Model\PaymentToken $paymentToken)
	{
		$hashKey = $paymentToken->getGatewayToken();
		if ($paymentToken->getCustomerId()) {
			$hashKey = $paymentToken->getCustomerId();
		}

		$hashKey .= $paymentToken->getPaymentMethodCode()
			. $paymentToken->getType()
			. $paymentToken->getTokenDetails();

		return $this->encryptor->getHash($hashKey);
	}
}
