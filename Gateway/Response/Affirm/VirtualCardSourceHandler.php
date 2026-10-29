<?php
namespace Fiserv\Payments\Gateway\Response\Affirm;

use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Response\HandlerInterface;

/**
 * Persists the source returned for an Affirm Virtual Card transaction.
 *
 * With a Virtual Card MID the order-placement response carries source.sourceType "PaymentToken" with a
 * masked card, instead of sourceType "Affirm". Only non-sensitive fields are kept (no PAN, CVV or expiration),
 * so later capture/refund/void can be recognised as card (Charges) transactions.
 */
class VirtualCardSourceHandler implements HandlerInterface
{
	const KEY_SOURCE_TYPE = 'vcn_source_type';
	const KEY_CARD_SCHEME = 'vcn_card_scheme';
	const KEY_CARD_BIN = 'vcn_card_bin';
	const KEY_CARD_LAST4 = 'vcn_card_last4';

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

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
	public function handle(array $handlingSubject, array $response)
	{
		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();
		$orderIncrementId = $paymentDO->getOrder()->getOrderIncrementId();

		$chResponse = $this->subjectReader->readChResponse($response)[HttpClient::RESPONSE_KEY];
		$source = $chResponse['source'] ?? [];
		$card = $source['card'] ?? [];

		if (empty($source['sourceType'])) {
			$this->logger->logWarning(1, "Affirm Virtual Card response contained no source", "Order ID: $orderIncrementId");
			return;
		}

		$payment->setAdditionalInformation(self::KEY_SOURCE_TYPE, $source['sourceType']);

		if (!empty($card['scheme'])) {
			$payment->setAdditionalInformation(self::KEY_CARD_SCHEME, $card['scheme']);
		}
		if (!empty($card['bin'])) {
			$payment->setAdditionalInformation(self::KEY_CARD_BIN, $card['bin']);
		}
		if (!empty($card['last4'])) {
			$payment->setAdditionalInformation(self::KEY_CARD_LAST4, $card['last4']);
			$payment->setCcLast4($card['last4']);
		}

		$this->logger->logDebug(3,"Affirm Virtual Card source stored: " . $source['sourceType'], "Order ID: $orderIncrementId");
	}
}
