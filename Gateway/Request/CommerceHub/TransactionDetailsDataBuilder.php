<?php
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config; 
use Fiserv\Payments\Model\Source\CommerceHub\TokenizationStrategy;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Payment Data Builder
 */
abstract class TransactionDetailsDataBuilder implements BuilderInterface
{
	const TXN_DETAILS_KEY = "transactionDetails";
	const KEY_CREATE_TOKEN = 'T';
	const CAPTURE = true;
	const AUTHORIZE = false;

	private $logger;
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
		
		$isVirtualCard = $payment->getMethod() === \Fiserv\Payments\Gateway\Config\AffirmVirtualCard\Config::CODE;
		$tokenStrat = $this->chConfig->getTokenStrategy();
		$createToken = !$isVirtualCard && (!empty($data[VaultConfigProvider::IS_ACTIVE_CODE]) || $tokenStrat === TokenizationStrategy::ALWAYS);
		$txnDetails->setCreateToken($createToken);
		if ($createToken == true) 
		{ 
        	$payment->setStoreVault(self::KEY_CREATE_TOKEN);
		}

		$captureFlag = $this->getCaptureFlag();
		
		$txnDetails->setCaptureFlag($captureFlag);
		$txnDetails->setMerchantOrderId($orderDO->getOrderIncrementId());
		$txnDetails->setMerchantTransactionId($isVirtualCard ? 'avc-' . $orderIncrementId : uniqid());
		$txnDetails->setAccountVerification(false);

		$this->logger->logDebug(3, "Transaction Details Data Builder:\n" . $txnDetails->__toString(), "Order ID: $orderIncrementId");

		return [ self::TXN_DETAILS_KEY => $txnDetails ];
	}

	/**
	 * Get Capture Flag
	 * @return bool
	 */
	abstract protected function getCaptureFlag();
}
