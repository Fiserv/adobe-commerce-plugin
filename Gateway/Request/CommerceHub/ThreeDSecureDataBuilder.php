<?php

namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\AdditionalData3DS;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config; 
use Fiserv\Payments\Observer\CommerceHub\DataAssignObserver;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * 3D Secure Data Builder
 */
class ThreeDSecureDataBuilder implements BuilderInterface
{
	const KEY_3DS_DATA = "three_d_secure_data";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var SubjectReader
	 */
	protected $subjectReader;

	private $chConfig;

	/**
	 * @param MultiLevelLogger $logger
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(
		SubjectReader $subjectReader,
		Config $chConfig,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->chConfig = $chConfig;
		$this->logger = $logger;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		if (!$this->chConfig->isThreeDSEnabled())
		{
			return [];
		}
	
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$data = $payment->getAdditionalInformation();
		$orderIncrementId = $paymentDO->getOrder()->getOrderIncrementId();

		$threeDSecureData = new AdditionalData3DS();
		$threeDSId = isset($data[DataAssignObserver::THREE_D_SECURE_KEY]) ? $data[DataAssignObserver::THREE_D_SECURE_KEY] : "";

		// 3D Secure data may not be available if transaction was run outside of customer flow (e.g. admin panel)
		if ($threeDSId === "")
		{
			return [];
		}

		$threeDSecureData->setAuthenticationTransactionId($threeDSId);

		$this->logger->logDebug(3, "3D Secure Data Builder:\n" . $threeDSecureData->__toString(), "Order ID: $orderIncrementId");
		return [ self::KEY_3DS_DATA => $threeDSecureData ];
	}
}
