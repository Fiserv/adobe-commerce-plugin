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
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;
use Fiserv\Payments\Logger\MultiLevelLogger;

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
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
		$orderIncrementId = $orderDO->getOrderIncrementId();




		$tokenData = $payment->getAdditionalInformation(DataAssignObserver::PAYMENT_TOKEN_KEY);
		$tokenSource = $payment->getAdditionalInformation(DataAssignObserver::TOKEN_SOURCE_KEY);
		$expMonth = $payment->getAdditionalInformation(DataAssignObserver::EXP_MONTH_KEY);
		$expYear = $payment->getAdditionalInformation(DataAssignObserver::EXP_YEAR_KEY);

		$nameOnCard = '';
		$vaultPaymentToken = $payment->getExtensionAttributes()->getVaultPaymentToken();
		if ($vaultPaymentToken instanceof \Magento\Vault\Api\Data\PaymentTokenInterface) {
			$details = $vaultPaymentToken->getTokenDetails();
			if (!empty($details)) {
				$detailsArray = json_decode($details, true);
				if (isset($detailsArray['nameOnCard'])) {
					$nameOnCard = $detailsArray['nameOnCard'];
				}
			}
		}

		$source = new PaymentToken();
		$source->setSourceType(self::PAYMENT_TOKEN_SOURCE_TYPE);
		$source->setTokenData($tokenData);
		$source->setTokenSource($tokenSource);
		$source->setDeclineDuplicates(false);

		if ($this->chConfig->isVaultCvvEnabled())
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
}
