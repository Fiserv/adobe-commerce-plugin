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

/**
 * Token Payment Data Builder
 */
class TokenSourceDataBuilder implements BuilderInterface
{
	use Formatter;

	const TOKEN_SOURCE_KEY = "tokenSource";
	const PAYMENT_TOKEN_SOURCE_TYPE = "PaymentToken";

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(SubjectReader $subjectReader)
	{
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
		
		$tokenData = $payment->getAdditionalInformation(DataAssignObserver::PAYMENT_TOKEN_KEY);
		$tokenSource = $payment->getAdditionalInformation(DataAssignObserver::TOKEN_SOURCE_KEY);
		$expMonth = $payment->getAdditionalInformation(DataAssignObserver::EXP_MONTH_KEY);
		$expYear = $payment->getAdditionalInformation(DataAssignObserver::EXP_YEAR_KEY);
		
		$source = new PaymentToken();
		$source->setSourceType(self::PAYMENT_TOKEN_SOURCE_TYPE);
		$source->setTokenData($tokenData);
		$source->setTokenSource($tokenSource);
		$source->setDeclineDuplicates(false);

		$card = new Card();
		$card->setExpirationMonth($expMonth);
		$card->setExpirationYear($expYear);

		$source->setCard($card);	

		return [ self::TOKEN_SOURCE_KEY => $source ];
	}
}
