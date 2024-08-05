<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Class CardDetailsHandler
 */
class CardDetailsHandler implements HandlerInterface
{
	const CARD_NUMBER = "cc_number";

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(
		Config $config,
		SubjectReader $subjectReader
	) {
		$this->config = $config;
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $handlingSubject, array $response)
	{
		$chResponse = $this->subjectReader->readChResponse($response)[\Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient::RESPONSE_KEY];

		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();
		ContextHelper::assertOrderPayment($payment);

		$cardDetails = $chResponse["source"]["card"];
		
		$payment->setCcLast4($cardDetails["last4"]);
		$payment->setCcExpMonth($cardDetails["expirationMonth"]);
		$payment->setCcExpYear($cardDetails["expirationYear"]);
		$payment->setCcType($this->getCreditCardType($cardDetails["scheme"]));
	}

	/**
	 * Get type of credit card mapped from CommerceHub
	 *
	 * @param string $type
	 * @return array
	 */
	private function getCreditCardType($type)
	{
		$replaced = str_replace(' ', '-', strtolower($type));
		$mapper = $this->config->getCcTypesMapper();

		return $mapper[strtoupper($replaced)];
	}
}
