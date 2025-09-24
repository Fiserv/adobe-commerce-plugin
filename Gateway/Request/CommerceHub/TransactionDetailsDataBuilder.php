<?php
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Source\CommerceHub\TokenizationStrategy;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Fiserv\Payments\Logger\MultiLevelLogger;

abstract class TransactionDetailsDataBuilder implements BuilderInterface
{
	const TXN_DETAILS_KEY = "transactionDetails";
	const KEY_CREATE_TOKEN = 'T';
	const CAPTURE = true;
	const AUTHORIZE = false;

	/** @var MultiLevelLogger */
	private $logger;

	/** @var SubjectReader */
	protected $subjectReader;

	private $chConfig;

	public function __construct(
		SubjectReader $subjectReader,
		Config $chConfig,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->chConfig = $chConfig;
		$this->logger = $logger;
	}

	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
		$orderIncrementId = $orderDO->getOrderIncrementId();

		$txnDetails = new TransactionDetails();
		$data = $payment->getAdditionalInformation();

		$tokenStrat = $this->chConfig->getTokenStrategy();
		$createToken = !empty($data[VaultConfigProvider::IS_ACTIVE_CODE]) || $tokenStrat === TokenizationStrategy::ALWAYS;
		$txnDetails->setCreateToken($createToken);

		if ($createToken == true) {
			$payment->setStoreVault(self::KEY_CREATE_TOKEN);
		}

		$captureFlag = $this->getCaptureFlag();
		$txnDetails->setCaptureFlag($captureFlag);
		$txnDetails->setMerchantOrderId($orderDO->getOrderIncrementId());
		$txnDetails->setMerchantTransactionId(uniqid());
		$txnDetails->setAccountVerification(false);

		// ✅ Log session_id and full additionalInformation
		$this->logger->logInfo(1, 'TransactionDetailsDataBuilder session_id logging', json_encode([
			'session_id' => $data['session_id'] ?? null,
			'additionalInformation' => $data
		]));

		$this->logger->logDebug(3, "TransactionDetailsDataBuilder:\n" . $txnDetails->__toString(), "OrderID:$orderIncrementId");

		return [self::TXN_DETAILS_KEY => $txnDetails];
	}

	abstract protected function getCaptureFlag();
}
