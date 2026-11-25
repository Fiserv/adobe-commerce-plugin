<?php
namespace Fiserv\Payments\Gateway\Request\CashApp;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentToken;
use Fiserv\Payments\Model\CashApp\TokenManagement;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;

/** Resolves the saved Cash App token by public hash; sends only the token (no card block, no CVV session). */
class TokenSourceDataBuilder implements BuilderInterface
{
	const TOKEN_SOURCE_KEY = 'tokenSource';
	const PAYMENT_TOKEN_SOURCE_TYPE = 'PaymentToken';

	private $subjectReader;
	private $tokenManagement;
	private $encryptor;
	private $extensionFactory;

	public function __construct(
		SubjectReader $subjectReader,
		TokenManagement $tokenManagement,
		EncryptorInterface $encryptor,
		OrderPaymentExtensionInterfaceFactory $extensionFactory
	) {
		$this->subjectReader = $subjectReader;
		$this->tokenManagement = $tokenManagement;
		$this->encryptor = $encryptor;
		$this->extensionFactory = $extensionFactory;
	}

	public function build(array $buildSubject)
	{
		$payment = $this->subjectReader->readPayment($buildSubject)->getPayment();
		$token = $this->tokenManagement->getAvailable(
			(int)$payment->getOrder()->getCustomerId(),
			(string)$payment->getAdditionalInformation('public_hash')
		);
		if ($token === null) {
			throw new LocalizedException(__('Saved Cash App Pay is unavailable. Please use Cash App Pay again.'));
		}

		// link vault token to the payment so that the repository plugin can persist it to the order
		$extension = $payment->getExtensionAttributes() ?: $this->extensionFactory->create();
		$extension->setVaultPaymentToken($token);
		$payment->setExtensionAttributes($extension);

		$source = new PaymentToken();
		$source->setSourceType(self::PAYMENT_TOKEN_SOURCE_TYPE);
		$source->setTokenData($this->encryptor->decrypt($token->getGatewayToken()));
		$source->setTokenSource(json_decode($token->getTokenDetails(), true)['tokenSource']);
		return [self::TOKEN_SOURCE_KEY => $source];
	}
}
