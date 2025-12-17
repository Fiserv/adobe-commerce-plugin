<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Observer\CommerceHub\DataAssignObserver;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentToken;
use Fiserv\Payments\Lib\CommerceHub\Model\Card;
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
		$tokenData = $payment->getAdditionalInformation(DataAssignObserver::PAYMENT_TOKEN_KEY);
		$tokenSource = $payment->getAdditionalInformation(DataAssignObserver::TOKEN_SOURCE_KEY);
		$expMonth = $payment->getAdditionalInformation(DataAssignObserver::EXP_MONTH_KEY);
		$expYear = $payment->getAdditionalInformation(DataAssignObserver::EXP_YEAR_KEY);
		$nameOnCard = '';
		$vaultPaymentToken = $payment->getExtensionAttributes()->getVaultPaymentToken();

		if ($vaultPaymentToken instanceof \Magento\Vault\Api\Data\PaymentTokenInterface) {
			$details = $vaultPaymentToken->getTokenDetails();
			if (!empty($details) && is_string($details)) {
				$detailsArray = json_decode($details, true);
				if (is_array($detailsArray) && isset($detailsArray['nameOnCard'])) {
					$nameOnCard = (string)$detailsArray['nameOnCard'];
				}
			}
		}

		$normalizedTokenData = $this->normalizeTokenData($tokenData);
		$source = new PaymentToken();
		$source->setSourceType(self::PAYMENT_TOKEN_SOURCE_TYPE);
		$source->setTokenData($normalizedTokenData);
		$source->setTokenSource($tokenSource);
		$source->setDeclineDuplicates(false);

		$card = new Card();
		if (!empty($expMonth)) {
			$card->setExpirationMonth($expMonth);
		}
		if (!empty($expYear)) {
			$card->setExpirationYear($expYear);
		}
		if ($nameOnCard) {
			$card->setNameOnCard($nameOnCard);
		}

		$source->setCard($card);

		$this->logger->logDebug(3, "Token Source Data Builder:\n" . $source->__toString(), "Order ID: $orderIncrementId");

		return [ self::TOKEN_SOURCE_KEY => $source ];
	}

	/**
	 * Normalize token data by stripping persistence suffixes commonly appended for storage.
	 *
	 * @param mixed $token
	 * @return string|null
	 */
	private function normalizeTokenData($token)
	{
		if ($token === null) { return null; }
		if (!is_string($token)) { return $token; }

		$token = trim($token);
		if ($token === '') { return null; }

		$parts = preg_split('/(\$\$|=)/', $token, 2);
		$raw = $parts[0] ?? $token;
		$raw = trim($raw);

		return $raw !== '' ? $raw : null;
	}
}