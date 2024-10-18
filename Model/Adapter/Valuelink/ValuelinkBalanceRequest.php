<?php
namespace Fiserv\Payments\Model\Adapter\Valuelink;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Source\CommerceHub\ApiEnvironment;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Gateway\Request\CommerceHub\SessionSourceDataBuilder;
use Fiserv\Payments\Logger\MultiLevelLogger;

use Fiserv\Payments\Lib\CommerceHub\Model\GiftCardRequest;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentSession;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails;

class ValuelinkBalanceRequest
{
	const BALANCE_INQUIRY_ENDPOINT = 'payments-vas/v1/accounts/balance-inquiry';

	// CommerceHub Credentials Request Keys
	const KEY_SECURITY_CODE_TYPE = 'securityCodeType';
	const KEY_URL = 'url';
	const KEY_ENDING_BALANCE = 'endingBalance';
	const KEY_CURRENCY = 'currency';
	const KEY_RESPONSE_MESSAGE = 'responseMessage';
	const GIFT_CATEGORY = 'GIFT';
	const GIFT_SUB_CATEGORY = 'GIFT_SOLUTIONS';

	const KEY_PAYMENT_RECEIPT = 'paymentReceipt';
	const KEY_PROCESSOR_RESPONSE_DETAILS = 'processorResponseDetails';
	const KEY_BALANCES = 'balances';

	/**
	 * @var Config
	 */
	private $chConfig;

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
		ChHttpAdapter $httpAdapter,
		MultiLevelLogger $logger
	) {
		$this->chConfig = $config;
		$this->httpAdapter = $httpAdapter;
		$this->logger = $logger;
	}

	/**
	* Retrieve assoc array of
	* CommerceHub CredentialsRequest info
	* 
	* @return array
	*/
	public function requestBalance($sessionId)
	{
		$data = $this->getBalanceInquiryPayload($this->getMerchantId(), $this->getTerminalId(), $sessionId);

		$this->logger->logInfo(1, "Sending balance inquiry...");
		$this->logger->logInfo(3, "BALANCE INQUIRY REQUEST INFO");
		$this->logger->logInfo(3, "Payload:\n" . print_r($data, true));
		$chResponse = $this->httpAdapter->sendRequest($data, self::BALANCE_INQUIRY_ENDPOINT);

		
		return $this->parseChBalanceInquiryResponse($chResponse);
	}

	private function parseChBalanceInquiryResponse($chResponse) {
		$statusCode = $chResponse->getStatusCode();
		$response = $chResponse->getResponse();
		$headerLength = $chResponse->getHeaderLength();
		$body = $chResponse->getBody();

		$this->logger->logInfo(1, "Response received for balance inquiry");
		$this->logger->logInfo(3, "BALANCE INQUIRY RESPONSE INFO");
		$this->logger->logInfo(3, "Response Headers:\n" . print_r($chResponse->getHeaders(), true));
		$this->logger->logInfo(3, "Response Body:\n" . json_encode(json_decode($body), JSON_PRETTY_PRINT));

		$header = [];

		foreach(explode("\r\n", trim(substr($response, 0, $headerLength))) as $row) {
			if(preg_match('/(.*?): (.*)/', $row, $matches)) {
				$header[$matches[1]] = $matches[2];
			}
		}

		$bodyArray = json_decode($body, true);

		$data = [];
		if ($statusCode === 201) {
			$data[self::KEY_ENDING_BALANCE] = $bodyArray[self::KEY_PAYMENT_RECEIPT][self::KEY_BALANCES][0][self::KEY_ENDING_BALANCE];
			$data[self::KEY_CURRENCY] = $bodyArray[self::KEY_PAYMENT_RECEIPT][self::KEY_BALANCES][0][self::KEY_CURRENCY];
			$data[self::KEY_RESPONSE_MESSAGE] = $bodyArray[self::KEY_PAYMENT_RECEIPT][self::KEY_PROCESSOR_RESPONSE_DETAILS][self::KEY_RESPONSE_MESSAGE];
		} else {
			throw new \Exception('CommerceHub Valuelink  BalanceInquiry request HTTP error code: ' . $statusCode, 1);
		};

		return $data;
	}

	private function getBalanceInquiryPayload($merchantId, $terminalId, $sessionId): GiftCardRequest{
		$payload = new GiftCardRequest();
		$source = new PaymentSession();
		$source->setSourceType(SessionSourceDataBuilder::PAYMENT_SESSION_SOURCE_TYPE);
		$source->setSessionId($sessionId);

		$merchantDetails = new MerchantDetails();
		$merchantDetails->setMerchantId($merchantId);
		$merchantDetails->setTerminalId($terminalId);

		$payload->setMerchantDetails($merchantDetails);
		$payload->setSource($source);

		return $payload;
	}

	private function getTerminalId() {
		return  $this->chConfig->getTerminalId();
    	}

	private function getMerchantId() {
		return $this->chConfig->getMerchantId();
	}
}
