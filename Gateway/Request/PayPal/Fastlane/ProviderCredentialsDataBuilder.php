<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal\Fastlane;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Observer\CommerceHub\DataAssignObserver;
use Fiserv\Payments\Lib\CommerceHub\Model\ProviderCredential;
use Fiserv\Payments\Lib\CommerceHub\Model\Attribute;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Fastlane Provider Credentials Data Builder
 */
class ProviderCredentialsDataBuilder implements BuilderInterface
{
	use Formatter;

	const PROVIDER_CREDENTIALS_KEY = "providerCreds";
	const SESSION_ID_KEY = "fastlane_session_id";

	const PAYPAL_CREDENTIAL_TYPE = "PAYPAL";
	const FASTLANE_SESSION_KEY = "FASTLANE_SESSION_ID";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;
	
	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @param MultiLevelLogger $logger
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
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
		
		$sessionId = $payment->getAdditionalInformation(self::SESSION_ID_KEY);

		$attr = new Attribute();
		$attr->setKey(self::FASTLANE_SESSION_KEY);
		$attr->setValue($sessionId);

		$creds = new ProviderCredential();
		$creds->setCredentialType(self::PAYPAL_CREDENTIAL_TYPE);
		$creds->setAttributes([$attr]);

		$this->logger->logDebug(3, "Fastlane Provider Credentials Data Builder:\n" . $creds->__toString(), "Order ID: $orderIncrementId");

		return [ self::PROVIDER_CREDENTIALS_KEY => $creds ];
	}
}
