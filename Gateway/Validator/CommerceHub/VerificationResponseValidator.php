<?php

namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

/**
 * Validates account verification response before payment transaction runs.
 */
class VerificationResponseValidator extends AbstractValidator
{
	const HTTP_OK = 200;
	const HTTP_CREATED = 201;

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 * @param MultiLevelLogger $logger
	 */
	public function __construct(
		ResultInterfaceFactory $resultFactory,
		SubjectReader $subjectReader,
		MultiLevelLogger $logger
	) {
		parent::__construct($resultFactory);
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
	}

	/**
	 * @inheritdoc
	 */
	public function validate(array $validationSubject): ResultInterface
	{
		$chRawResponse = $this->subjectReader->readChResponseFromResponse($validationSubject);
		$statusCode = $chRawResponse[HttpClient::STATUS_CODE_KEY] ?? null;
		$orderIncrementId = null;

		try {
			$orderIncrementId = $this->subjectReader->readPayment($validationSubject)->getOrder()->getOrderIncrementId();
		} catch (\Exception $e) {
			// Keep validation resilient when order context is unavailable.
		}

		if (!in_array($statusCode, [self::HTTP_OK, self::HTTP_CREATED], true)) {
			$this->logger->logError(2, 'Account verification failed with unsuccessful status code: ' . (string)$statusCode, 'Order ID: ' . ($orderIncrementId ?? 'Not found'));
			return $this->createResult(false, ['Unable to verify card account details. Please retry your payment.'], [(string)$statusCode]);
		}

		$transactionState = SubjectReader::getValueSafely(
			$chRawResponse,
			'transactionState',
			[HttpClient::RESPONSE_KEY, 'gatewayResponse']
		);
		if (in_array($transactionState, ['DECLINED', 'GATEWAY_ERROR', 'TIMEOUT'], true)) {
			$this->logger->logError(2, 'Account verification returned failure state: ' . $transactionState, 'Order ID: ' . ($orderIncrementId ?? 'Not found'));
			return $this->createResult(false, ['Unable to verify card account details. Please retry your payment.'], [$transactionState]);
		}

		$this->logger->logInfo(1, 'Account verification succeeded', 'Order ID: ' . ($orderIncrementId ?? 'Not found'));
		return $this->createResult(true);
	}
}

