<?php
namespace Fiserv\Payments\Model\Adapter\Valuelink;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Gateway\Config\Valuelink\Config as ValuelinkConfig;
use Fiserv\Payments\Model\Source\CommerceHub\ApiEnvironment;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Gateway\Request\CommerceHub\SessionSourceDataBuilder;
use Magento\Payment\Model\MethodInterface;
use Magento\Framework\Exception\LocalizedException;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Lib\CommerceHub\Model\ChargesRequest;
use Fiserv\Payments\Lib\CommerceHub\Model\Amount;
use Fiserv\Payments\Lib\CommerceHub\Model\ReferenceTransactionDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\SplitShipment;


class ValuelinkCaptureRequest
{
	const CAPTURE_ENDPOINT = 'payments/v1/charges';

	/**
	 * @var Config
	 */
	private $chConfig;

	/**
	 * @var ValuelinkConfig
	 */
	private $valuelinkConfig;

	/**
	 * @ChHttpAdapter
	 */
	private $httpAdapter;

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 */
	public function __construct(
		Config $config,
		ValuelinkConfig $valuelinkConfig,
		ChHttpAdapter $httpAdapter,
		MultiLevelLogger $logger
	) {
		$this->chConfig = $config;
		$this->valuelinkConfig = $valuelinkConfig;
		$this->httpAdapter = $httpAdapter;
		$this->logger = $logger;
	}

	/**
	 * Retrieve assoc array of
	 * CommerceHub CredentialsRequest info
	 *
	 * @return array
	 */
	public function captureValuelinkRequest(ChargesRequest $payload)
	{
		$this->logger->logInfo(1, "Sending gift card request...");
		$this->logger->logInfo(3, "TXN REQUEST INFO");
		$this->logger->logInfo(3, "Payload:\n" . print_r($payload, true));
		$chResponse = $this->httpAdapter->sendRequest($payload, self::CAPTURE_ENDPOINT);
		return $this->parseCaptureResponse($chResponse);
	}

	private function parseCaptureResponse($chResponse)
	{
		$statusCode = $chResponse->getStatusCode();
		$response = $chResponse->getResponse();
		$headerLength = $chResponse->getHeaderLength();
		$body = $chResponse->getBody();

		$this->logger->logInfo(1, "Response received for gift card request");
		$this->logger->logInfo(3, "TXN INQUIRY RESPONSE INFO");
		$this->logger->logInfo(3, "Response Headers:\n" . print_r($chResponse->getHeaders(), true));
		$this->logger->logInfo(3, "Response Body:\n" . json_encode(json_decode($body), JSON_PRETTY_PRINT));

		$header = [];

		foreach(explode("\r\n", trim(substr($response, 0, $headerLength))) as $row) {
			if(preg_match('/(.*?): (.*)/', $row, $matches)) {
				$header[$matches[1]] = $matches[2];
			}
		}

		$bodyArray = json_decode($body, true);

		if ($statusCode !== 201) {
			$this->logger->logError(1, "Transaction failure. Gift card response returned with unsuccessful status");
			$this->logger->logError(2, "Status Code: " . $statusCode);
			throw new \Exception('CommerceHub Valuelink Capture Request request HTTP error code: ' . $statusCode, 1);
		};

		return $bodyArray;
	}

	public function getValuelinkCapturePayload($txnId, $total, $previousCaptures, $finalCapture, $currency) : ChargesRequest
	{
		$payload = new ChargesRequest();

		$payload->setAmount($this->getAmount($total, $currency));
		$payload->setReferenceTransactionDetails($this->getReferenceTransactionDetails($txnId));
		$payload->setTransactionDetails($this->getTransactionDetails($previousCaptures, $finalCapture));
		$payload->setMerchantDetails($this->getMerchantDetails());

		return $payload;
	}

	private function getAmount($total, $currency) : Amount
	{
		$amount = new Amount();

		$amount->setTotal($total);
		$amount->setCurrency($currency);

		return $amount;
	}

	private function getReferenceTransactionDetails($txnId) : ReferenceTransactionDetails
	{
		$refTxn = new  ReferenceTransactionDetails();

		$refTxn->setReferenceTransactionId($txnId);

		return $refTxn;
	}

	private function getTransactionDetails($previousCaptures, $finalCapture) : TransactionDetails
	{
		$details = new TransactionDetails();

		$details->setCaptureFlag(true);
		$details->setMerchantTransactionId(uniqid());
		$details->setMerchantOrderId(uniqid());
		$splitShipment = new SplitShipment();
		$splitShipment->setFinalShipment($finalCapture);
		$splitShipment->setTotalCount($previousCaptures + 1);
		$details->setSplitShipment($splitShipment);

		return $details;
	}

	private function getMerchantDetails() : MerchantDetails
	{
		$merchantDetails = new MerchantDetails();

		$merchantDetails->setMerchantId($this->chConfig->getMerchantId());
		$merchantDetails->setTerminalId($this->chConfig->getTerminalId());

		return $merchantDetails;
	}
}
