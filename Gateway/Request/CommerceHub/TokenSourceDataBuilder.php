<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Observer\CommerceHub\DataAssignObserver;
use Fiserv\Payments\Gateway\Request\CommerceHub\SessionSourceDataBuilder;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentToken;
use Fiserv\Payments\Lib\CommerceHub\Model\Card;
use Fiserv\Payments\Model\System\Utils\PaymentTokenUtil;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Gateway\Config\Klarna\Config as KlarnaConfig;
use Fiserv\Payments\Model\Klarna\TokenManagement;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;
use Fiserv\Payments\Logger\MultiLevelLogger;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;

/**
 * Token Payment Data Builder
 */
class TokenSourceDataBuilder implements BuilderInterface
{
	use Formatter;

	const TOKEN_SOURCE_KEY = "tokenSource";
	const PAYMENT_TOKEN_SOURCE_TYPE = "PaymentToken";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	private $chConfig;

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	private $appState;

	private $tokenManagement;

	private $userContext;

	private $encryptor;

	private $extensionFactory;

	/**
	 * @param MultiLevelLogger $logger
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(
		SubjectReader $subjectReader,
		Config $chConfig,
		State $appState,
		MultiLevelLogger $logger,
		TokenManagement $tokenManagement,
		UserContextInterface $userContext,
		EncryptorInterface $encryptor,
		OrderPaymentExtensionInterfaceFactory $extensionFactory
	) {
		$this->subjectReader = $subjectReader;
		$this->chConfig = $chConfig;
		$this->appState = $appState;
		$this->logger = $logger;
		$this->tokenManagement = $tokenManagement;
		$this->userContext = $userContext;
		$this->encryptor = $encryptor;
		$this->extensionFactory = $extensionFactory;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
		$orderIncrementId = $orderDO->getOrderIncrementId();

		if (in_array($payment->getMethod(), [KlarnaConfig::CODE, KlarnaConfig::VAULT_CODE], true)) {
			return $this->buildKlarnaSource($payment);
		}

		// load the gateway token from the server-side vault
		$vaultPaymentToken = $payment->getExtensionAttributes()->getVaultPaymentToken();
		if (!$vaultPaymentToken instanceof \Magento\Vault\Api\Data\PaymentTokenInterface) {
			throw new \Exception
			("Vault payment token not found. Cannot process token payment without a verified vault record.");
		}

		$tokenData = PaymentTokenUtil::getTokenDataFromPersistenceFormat(
			(string)$vaultPaymentToken->getGatewayToken()
		);
		$tokenSource = $payment->getAdditionalInformation(DataAssignObserver::TOKEN_SOURCE_KEY);
		$expMonth = $payment->getAdditionalInformation(DataAssignObserver::EXP_MONTH_KEY);
		$expYear = $payment->getAdditionalInformation(DataAssignObserver::EXP_YEAR_KEY);

		// Normalize expiration year: CommerceHub requires exactly 4 digits.
		// Some stored vault tokens may have a 2-digit year (e.g. "25" instead of "2025").
		if (!empty($expYear) && strlen((string)$expYear) === 2 && ctype_digit((string)$expYear)) {
			$expYear = '20' . $expYear;
		}

		$nameOnCard = '';
		$details = $vaultPaymentToken->getTokenDetails();
		if (!empty($details)) {
			$detailsArray = json_decode($details, true);
			if (isset($detailsArray['nameOnCard'])) {
				$nameOnCard = $detailsArray['nameOnCard'];
			}
		}

		$source = new PaymentToken();
		$source->setSourceType(self::PAYMENT_TOKEN_SOURCE_TYPE);
		$source->setTokenData($tokenData);
		$source->setTokenSource($tokenSource);
		$source->setDeclineDuplicates(false);

		if ($this->chConfig->isVaultCvvEnabled() && !$this->isAdminArea())
		{
			$sessionId = $payment->getAdditionalInformation(DataAssignObserver::SESSION_ID_KEY);
			if (empty($sessionId))
			{
				throw new \Exception("CVV is required but no SessionId was found.");
			}
			$source->setSessionId($sessionId);
		}

		$card = new Card();
		$card->setExpirationMonth($expMonth);
		$card->setExpirationYear($expYear);
		if($nameOnCard) {
			$card->setNameOnCard($nameOnCard);
		}

		$source->setCard($card);	

		$this->logger->logDebug(3, "Token Source Data Builder:\n" . $source->__toString(), "Order ID: $orderIncrementId");

		return [ self::TOKEN_SOURCE_KEY => $source ];
	}

	/**
	 * Klarna has no vault token on the payment yet: resolve the public hash server-side, apply the
	 * eligibility rules, and send only the token (no card block, no CVV session).
	 */
	private function buildKlarnaSource($payment): array
	{
		$order = $payment->getOrder();
		$customerId = $order->getCustomerId();
		if (!$customerId || $order->getCustomerIsGuest() ||
			$this->userContext->getUserType() !== UserContextInterface::USER_TYPE_CUSTOMER ||
			$this->userContext->getUserId() != $customerId) {
			throw new LocalizedException(__('Please sign in to use saved Klarna.'));
		}

		$token = $this->tokenManagement->getAvailable(
			$customerId,
			(string)$payment->getAdditionalInformation('public_hash'),
			$order->getStoreId()
		);
		if ($token === null) {
			$this->logger->logError(1, 'Klarna stored-token checkout rejected: token unavailable or scope mismatch.',
				'Order ID: ' . $order->getIncrementId());
			throw new LocalizedException(__('Saved Klarna is unavailable. Please use Klarna again.'));
		}

		try {
			$tokenData = $this->encryptor->decrypt($token->getGatewayToken());
		} catch (\Exception $exception) {
			$this->logger->logError(1, 'Klarna stored-token checkout rejected: token decryption failed.',
				'Order ID: ' . $order->getIncrementId());
			throw new LocalizedException(__('Saved Klarna is unavailable.'));
		}

		// link vault token to the payment so that the repository plugin can persist it to the order
		$extension = $payment->getExtensionAttributes() ?: $this->extensionFactory->create();
		$extension->setVaultPaymentToken($token);
		$payment->setExtensionAttributes($extension);

		$source = new PaymentToken();
		$source->setSourceType(self::PAYMENT_TOKEN_SOURCE_TYPE);
		$source->setTokenData($tokenData);
		$source->setTokenSource(json_decode($token->getTokenDetails(), true)['tokenSource']);
		return [ self::TOKEN_SOURCE_KEY => $source ];
	}

	private function isAdminArea(): bool
	{
		try {
			return $this->appState->getAreaCode() === Area::AREA_ADMINHTML;
		} catch (LocalizedException $exception) {
			return false;
		}
	}
}
