<?php
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Fiserv\Payments\Logger\MultiLevelLogger;

class TransactionInquiryRequest
{
	const SESSION_INQUIRY_ENDPOINT = 'v1/inquiries';

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @var Config
	 */
	private $chConfig;

	/**
	 * @var ChHttpAdapter
	 */
	private $httpAdapter;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 */
	public function __construct(
		Config $config,
		ChHttpAdapter $httpAdapter,
		MultiLevelLogger $logger
	) {
		$this->chConfig = $config;
		$this->httpAdapter = $httpAdapter;
		$this->logger = $logger;
	}

	/**
	 * Look up the outcome of a hosted-checkout session by the sessionId
	 * returned from the `v1/sessions/create` call.
	 *
	 * Used by Pay by Link to independently confirm a charge before trusting
	 * the completion redirect, since the redirect's query params are
	 * shopper-controlled and unsigned. Hits `v1/inquiries` and returns the
	 * raw decoded response body (session status + linked transactions).
	 *
	 * @param string $sessionId
	 * @return array The decoded response body (contains `session` and `linkedTransactions`).
	 */
	public function executeSessionInquiry($sessionId)
	{
		$this->logger->logInfo(1, "Initiating Session Inquiry Request");

		$payload = [
			'merchantDetails' => [
				'merchantId' => $this->getMerchantId(),
				'terminalId' => $this->getTerminalId(),
			],
			'session' => [
				'sessionId' => $sessionId,
			],
		];

		$this->logger->logDebug(3, "Session Inquiry Request Payload:\n" . json_encode($payload, JSON_PRETTY_PRINT));

		$response = $this->httpAdapter->sendRequest($payload, self::SESSION_INQUIRY_ENDPOINT);

		return $this->parseSessionInquiryResponse($response);
	}

	/**
	 * @param object $httpResponse
	 * @return array
	 */
	private function parseSessionInquiryResponse($httpResponse)
	{
		$statusCode = $httpResponse->getStatusCode();
		$bodyArray = json_decode($httpResponse->getBody(), true);

		$this->logger->logDebug(3, "Session Inquiry Response (HTTP {$statusCode}):\n" . json_encode($bodyArray, JSON_PRETTY_PRINT));

		if ($statusCode >= 200 && $statusCode < 300 && is_array($bodyArray)) {
			$this->logger->logInfo(1, "Session Inquiry request success");
			return $bodyArray;
		}

		$this->logger->logError(1, "Session Inquiry request failure");
		$this->logger->logError(2, 'CommerceHub session inquiry request HTTP error code: ' . $statusCode);
		throw new \Exception('CommerceHub session inquiry request HTTP error code: ' . $statusCode, 1);
	}


	private function getMerchantId() {
		return $this->chConfig->getMerchantId();
	}

	private function getTerminalId() {
		return $this->chConfig->getTerminalId();
	}
}
