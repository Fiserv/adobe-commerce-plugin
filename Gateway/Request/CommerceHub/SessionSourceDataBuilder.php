<?php
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Observer\CommerceHub\DataAssignObserver;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentSession;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Payment Data Builder
 */
class SessionSourceDataBuilder implements BuilderInterface
{
	use Formatter;

	const SESSION_SOURCE_KEY = "sessionSource";
	const PAYMENT_SESSION_SOURCE_TYPE = "PaymentSession";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @param SubjectReader $subjectReader
	 * @param MultiLevelLogger $logger
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

		$sessionId = $payment->getAdditionalInformation(DataAssignObserver::SESSION_ID_KEY) ?? $buildSubject['session_id'] ?? $buildSubject['sessionId'] ?? null;

		if (!$sessionId) {
			$this->logger->logError(1, 'SessionSourceDataBuilder - Missing session_id', [
				'order_id' => $orderIncrementId,
				'buildSubject' => $buildSubject
			]);
			throw new \InvalidArgumentException('Missing session_id for PaymentSession');
		}

		// Use PaymentSession model to build source
		$source = new PaymentSession();
		$source->setSourceType(self::PAYMENT_SESSION_SOURCE_TYPE);
		$source->setSessionId($sessionId);

		$this->logger->logDebug(3, "Session Source Data Builder:\n" . $source->__toString(), "Order ID: $orderIncrementId");

		return [ self::SESSION_SOURCE_KEY => $source ];
	}
}
