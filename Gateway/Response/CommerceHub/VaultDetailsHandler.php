<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Fiserv\Payments\Lib\CommerceHub\BpResponse;
use Fiserv\Payments\Model\System\Utils\VaultPaymentTokenUtils;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionDetailsDataBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Fiserv\Payments\Model\System\Utils\PaymentTokenUtil;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Psr\Log\LoggerInterface;

/**
 * Vault Details Handler
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class VaultDetailsHandler implements HandlerInterface
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
	** @var LoggerInterface
	**/
	private $logger;

	/**
	 * VaultDetailsHandler constructor.
	 *
	 * @param PaymentTokenFactoryInterface $paymentTokenFactory
	 * @param OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
	 * @param SubjectReader $subjectReader
	 * @param Json|null $serializer
	 * @throws \RuntimeException
	 */
	public function __construct(
		Config $config,
		VaultPaymentTokenUtils $vaultPaymentTokenUtils,
		PaymentTokenFactoryInterface $paymentTokenFactory,
		OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
		SubjectReader $subjectReader,
		Json $serializer = null,
		LoggerInterface $logger
	) {
		$this->config = $config;
		$this->vaultPaymentTokenUtils = $vaultPaymentTokenUtils;
		$this->paymentTokenFactory = $paymentTokenFactory;
		$this->paymentExtensionFactory = $paymentExtensionFactory;
		$this->subjectReader = $subjectReader;
		$this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
		$this->logger = $logger;
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $handlingSubject, array $response)
	{
		
		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$chResponse = $this->subjectReader->readChResponse($response)[\Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient::RESPONSE_KEY];
		$payment = $paymentDO->getPayment();
		
		
		if(
			$payment->getStoreVault() === TransactionDetailsDataBuilder::KEY_CREATE_TOKEN &&
			$this->wasTokenRequestSuccessful($chResponse) &&
			!($this->vaultPaymentTokenUtils->doesTokenExist(
				$chResponse["paymentTokens"][0]["tokenData"], 
				$payment->getMethodInstance()->getCode(),  
				$payment->getOrder()->getCustomerId(),
				$chResponse["source"]["card"]["expirationMonth"],
				$chResponse["source"]["card"]["expirationYear"]
			))
		) {
			$paymentToken = $this->getVaultCardToken($chResponse);
			$extensionAttributes = $this->getExtensionAttributes($payment);
			$extensionAttributes->setVaultPaymentToken($paymentToken);
		}
		
	}

	/**
	 * Get vault payment token entity for payment card
	 *
	 * @param array $chResponse
	 * @return PaymentTokenInterface|null
	 */
	public function getVaultCardToken($chResponse)
	{
		$paymentToken = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
		
		$cardArray = $chResponse["source"]["card"];
		$tokenArray = $chResponse["paymentTokens"][0];

		// sorry to anyone reading this ternery
		// basically checking two places for the scheme:
		// 1. source.card.scheme
		// 2. cardDetails[0].detailedCardProduct
		$rawScheme = isset($cardArray["scheme"]) ? $cardArray["scheme"] : 
			( 
				(
					(!isset($chResponse["cardDetails"])) || 
					(!isset($chResponse["cardDetails"][0])) ||
					(!isset($chResponse["cardDetails"][0]["detailedCardProduct"])) 
				) ? "" : $chResponse["cardDetails"][0]["detailedCardProduct"]
			);

		$details = $this->convertDetailsToJSON([
			"type" => !empty($rawScheme) ? $this->getCreditCardType($rawScheme) : "",
			"maskedCC" => "************" . $cardArray["last4"],
			"expirationDate" => $cardArray["expirationMonth"] . "/" . $cardArray["expirationYear"],
			"tokenSource" => $tokenArray["tokenSource"],
			"tokenResponseCode" => $tokenArray["tokenResponseCode"],
			"tokenResponseDescription" => $tokenArray["tokenResponseDescription"]
		]);
		$paymentToken->setGatewayToken(PaymentTokenUtil::formatTokenDataForPersistence($tokenArray["tokenData"]));
		$paymentToken->setExpiresAt($this->getExpirationDate($cardArray));
		$paymentToken->setTokenDetails($details);

		return $paymentToken;
	}
	
	private function getCreditCardType($type)
	{
		$replaced = str_replace(' ', '-', strtolower($type));
		$mapper = $this->config->getCcTypesMapper();

		return $mapper[strtoupper($replaced)];
	}

	/**
	 * @param array $cardArray
	 * @return string
	 */
	private function getExpirationDate($cardArray)
	{
		$expDate = new \DateTime(
            $cardArray["expirationYear"]
            . '-'
            . $cardArray["expirationMonth"]
            . '-'
            . '01'
            . ' '
            . '00:00:00',
            new \DateTimeZone('UTC')
        );
        $expDate->add(new \DateInterval('P1M'));

        return $expDate->format('Y-m-d 00:00:00');
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

	private function wasTokenRequestSuccessful($chResponse) {
		return (
			isset($chResponse["paymentTokens"]) && 
			$chResponse["paymentTokens"][0] !== null &&
			isset($chResponse["paymentTokens"][0]["tokenResponseDescription"]) &&
			$chResponse["paymentTokens"][0]["tokenResponseDescription"] == "SUCCESS" &&
			isset($chResponse["paymentTokens"][0]["tokenData"])
		);
	}
}
