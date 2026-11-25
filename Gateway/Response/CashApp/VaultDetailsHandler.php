<?php
namespace Fiserv\Payments\Gateway\Response\CashApp;

use Fiserv\Payments\Gateway\Config\CashApp\Config as CashAppConfig;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\CashApp\TokenManagement;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/** Maps explicitly consented wallet tokens; native Vault generates hashes, persists, and links them. */
class VaultDetailsHandler implements HandlerInterface
{
	private $subjectReader;
	private $config;
	private $tokenManagement;
	private $tokenFactory;
	private $extensionFactory;
	private $encryptor;
	private $serializer;
	private $logger;

	public function __construct(
		SubjectReader $subjectReader,
		Config $config,
		TokenManagement $tokenManagement,
		PaymentTokenFactoryInterface $tokenFactory,
		OrderPaymentExtensionInterfaceFactory $extensionFactory,
		EncryptorInterface $encryptor,
		Json $serializer,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->config = $config;
		$this->tokenManagement = $tokenManagement;
		$this->tokenFactory = $tokenFactory;
		$this->extensionFactory = $extensionFactory;
		$this->encryptor = $encryptor;
		$this->serializer = $serializer;
		$this->logger = $logger;
	}

	public function handle(array $handlingSubject, array $response)
	{
		$payment = $this->subjectReader->readPayment($handlingSubject)->getPayment();
		$gatewayResponse = $this->subjectReader->readChResponse($response)[HttpClient::RESPONSE_KEY];
		try {
			$token = $this->getVaultToken($payment, $gatewayResponse);
			if ($token !== null) {
				$extension = $payment->getExtensionAttributes() ?: $this->extensionFactory->create();
				$extension->setVaultPaymentToken($token);
				$payment->setExtensionAttributes($extension);
			}
		} catch (\Throwable $exception) {
			$this->logger->logError(1, 'Cash App token mapping failed; gateway approval is unchanged.',
				'Order ID: ' . $payment->getOrder()->getIncrementId());
		}
	}

	private function getVaultToken(InfoInterface $payment, array $response): ?PaymentTokenInterface
	{
		$order = $payment->getOrder();
		$customerId = $order->getCustomerId();
		if ($payment->getMethod() !== CashAppConfig::CODE || !$customerId || $order->getCustomerIsGuest() ||
			$payment->getAdditionalInformation('public_hash') ||
			!$payment->getAdditionalInformation('is_active_payment_token_enabler') ||
			$this->tokenManagement->getOriginal($customerId) !== null) {
			return null;
		}

		$sessionId = $payment->getAdditionalInformation('payment_session');
		if (!$sessionId) {
			return null;
		}

		$storeId = $order->getStoreId();
		if ($response['gatewayResponse']['approvalStatus'] !== 'APPROVED' ||
			!in_array($response['gatewayResponse']['transactionState'], ['AUTHORIZED', 'CAPTURED'], true) ||
			$response['source']['sourceType'] !== 'CashApp' ||
			$response['session']['sessionId'] !== $sessionId ||
			$response['merchantDetails']['merchantId'] !== $this->config->getMerchantId($storeId) ||
			$response['merchantDetails']['terminalId'] !== $this->config->getTerminalId($storeId)) {
			return null;
		}

		$candidate = $response['paymentTokens'][0];
		if ($candidate['tokenResponseCode'] !== '000' || $candidate['tokenResponseDescription'] !== 'SUCCESS') {
			return null;
		}

		$encrypted = $this->encryptor->encrypt($candidate['tokenData']);

		$token = $this->tokenFactory->create();
		$token->setType('cashapp');
		$token->setCustomerId($customerId);
		$token->setPaymentMethodCode(CashAppConfig::CODE);
		$token->setTokenDetails($this->serializer->serialize([
			'type' => 'cashapp',
			'label' => 'Cash App Pay',
			'tokenSource' => $candidate['tokenSource'],
			'tokenIdentity' => $this->encryptor->getHash($candidate['tokenData']),
			'websiteId' => $order->getStore()->getWebsiteId(),
			'merchantId' => $this->config->getMerchantId($storeId),
			'terminalId' => $this->config->getTerminalId($storeId),
			'environment' => $this->config->getApiEnvironment($storeId),
		]));
		$token->setGatewayToken($encrypted);
		$token->setExpiresAt(null);
		$token->setIsVisible(true);
		$token->setIsActive(true);
		return $token;
	}
}
