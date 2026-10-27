<?php
namespace Fiserv\Payments\Gateway\Request\CashApp;

use Fiserv\Payments\Gateway\Config\CashApp\Config;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentToken;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\CashApp\TokenManagement;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;

/** Resolves a public hash server-side; never accepts raw wallet tokens from checkout. */
class TokenSourceDataBuilder implements BuilderInterface
{
	private $subjectReader;
	private $tokenManagement;
	private $userContext;
	private $encryptor;
	private $extensionFactory;
	private $logger;

	public function __construct(
		SubjectReader $subjectReader,
		TokenManagement $tokenManagement,
		UserContextInterface $userContext,
		EncryptorInterface $encryptor,
		OrderPaymentExtensionInterfaceFactory $extensionFactory,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->tokenManagement = $tokenManagement;
		$this->userContext = $userContext;
		$this->encryptor = $encryptor;
		$this->extensionFactory = $extensionFactory;
		$this->logger = $logger;
	}

	public function build(array $buildSubject)
	{
		$payment = $this->subjectReader->readPayment($buildSubject)->getPayment();
		$order = $payment->getOrder();
		$customerId = (int) $order->getCustomerId();
		if (!in_array($payment->getMethod(), [Config::CODE, Config::VAULT_CODE], true) || !$customerId || $order->getCustomerIsGuest() ||
			$this->userContext->getUserType() !== UserContextInterface::USER_TYPE_CUSTOMER ||
			(int) $this->userContext->getUserId() !== $customerId) {
			throw new LocalizedException(__('Please sign in to use saved Cash App Pay.'));
		}

		// The native Vault facade links the token on the payment; fall back to the submitted hash.
		$extension = $payment->getExtensionAttributes();
		$linked = $extension ? $extension->getVaultPaymentToken() : null;
		$publicHash = $linked ? (string) $linked->getPublicHash() : (string) $payment->getAdditionalInformation('public_hash');
		$token = $this->tokenManagement->getAvailable(
			$customerId,
			$publicHash,
			(int) $order->getStoreId()
		);
		if ($token === null) {
			$this->logger->logError(1, 'Cash App stored-token checkout rejected: token unavailable or scope mismatch.',
				'Order ID: ' . $order->getIncrementId());
			throw new LocalizedException(__('Saved Cash App Pay is unavailable. Please use Cash App Pay again.'));
		}

		try {
			$tokenData = $this->encryptor->decrypt($token->getGatewayToken());
		} catch (\Exception $exception) {
			$this->logger->logError(1, 'Cash App stored-token checkout rejected: token decryption failed.',
				'Order ID: ' . $order->getIncrementId());
			throw new LocalizedException(__('Saved Cash App Pay is unavailable.'));
		}
		if ($tokenData === '') {
			throw new LocalizedException(__('Saved Cash App Pay is unavailable.'));
		}

		$extension = $extension ?: $this->extensionFactory->create();
		$extension->setVaultPaymentToken($token);
		$payment->setExtensionAttributes($extension);

		$source = new PaymentToken();
		$source->setSourceType('PaymentToken');
		$source->setTokenData($tokenData);
		$source->setTokenSource('FISERV_SECURE_VAULT');
		return ['tokenSource' => $source];
	}
}
