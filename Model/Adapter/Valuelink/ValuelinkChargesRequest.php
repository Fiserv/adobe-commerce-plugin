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
use Fiserv\Payments\Helper\MerchantPartnerHelper;

use Fiserv\Payments\Lib\CommerceHub\Model\ChargesRequest;
use Fiserv\Payments\Lib\CommerceHub\Model\Amount;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentSession;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails;

class ValuelinkChargesRequest
{
	const CHARGES_ENDPOINT = 'payments/v1/charges';

	// CommerceHub Credentials Request Keys
	const KEY_SECURITY_CODE_TYPE = 'securityCodeType';
	const KEY_URL = 'url';
	const KEY_RESPONSE_MESSAGE = 'responseMessage';
	const KEY_ORIGIN = "ECOM";
	const KEY_POS_CODE = "CARD_NOT_PRESENT_ECOM";
	const KEY_ECI_INDICATOR = "CHANNEL_ENCRYPTED";
	
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
	public function chargeValuelinkCard(ChargesRequest $payload)
	{
		$merchantOrderId = $payload->getTransactionDetails()->getMerchantOrderId();
		$this->logger->logInfo(1, "Sending gift card request...", "Order ID: {$merchantOrderId}");
		$this->logger->logDebug(3, "TXN REQUEST INFO", "Order ID: {$merchantOrderId}");
		$this->logger->logDebug(3, "Payload:\n" . print_r($payload, true), "Order ID: {$merchantOrderId}");

		$chResponse = $this->httpAdapter->sendRequest($payload, self::CHARGES_ENDPOINT);
		return $this->parseChargesResponse($chResponse, $merchantOrderId);
	}

	private function parseChargesResponse($chResponse, $merchantOrderId) {
		$statusCode = $chResponse->getStatusCode();
		$response = $chResponse->getResponse();
		$headerLength = $chResponse->getHeaderLength();
		$body = $chResponse->getBody();

		$this->logger->logInfo(1, "Response received for gift card request", "Order ID: {$merchantOrderId}");
		$this->logger->logDebug(3, "TXN INQUIRY RESPONSE INFO", "Order ID: {$merchantOrderId}");
		$this->logger->logDebug(3, "Response Headers:\n" . print_r($chResponse->getHeaders(), true), "Order ID: {$merchantOrderId}");
		$this->logger->logError(2, "Response Body:\n" . json_encode(json_decode($body), JSON_PRETTY_PRINT), "Order ID: {$merchantOrderId}");

		$header = [];

		foreach(explode("\r\n", trim(substr($response, 0, $headerLength))) as $row) {
			if(preg_match('/(.*?): (.*)/', $row, $matches)) {
				$header[$matches[1]] = $matches[2];
			}
		}

		$bodyArray = json_decode($body, true);

		if ($statusCode !== 201) {
			$this->logger->logError(1, "Transaction failure. Gift card response returned with unsuccessful status", "Order ID: {$merchantOrderId}");
			$this->logger->logError(2, "Status Code: " . $statusCode, "Order ID: {$merchantOrderId}");
			
			throw new \Exception('CommerceHub Gift Card Charges Request  HTTP error code: ' . $statusCode, 1);
		};

		return $bodyArray;
	}

	public function getValuelinkChargesPayload($sessionId, $total, $currency, $merchantOrderId) : ChargesRequest
	{
		$payload = new ChargesRequest();
		$payload->setAmount($this->getAmount($total, $currency));
		$payload->setSource($this->getSource($sessionId));
		$payload->setTransactionDetails($this->getTransactionDetails($merchantOrderId));
		$payload->setTransactionInteraction($this->getTransactionInteraction());
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
	
	private function getSource($sessionId) : PaymentSession
	{
		$source = new PaymentSession();
		$source->setSourceType(SessionSourceDataBuilder::PAYMENT_SESSION_SOURCE_TYPE);
		$source->setSessionId($sessionId);
		
		return $source;
	}

	private function getTransactionDetails($merchantOrderId) : TransactionDetails
	{
		$details = new TransactionDetails();
		
		$details->setCaptureFlag($this->valuelinkConfig->getPaymentAction() == MethodInterface::ACTION_AUTHORIZE_CAPTURE);
		$details->setMerchantTransactionId(uniqid());
		$details->setMerchantOrderId($merchantOrderId);
		$details->setCreateToken(false);

		return $details;
	}

	private function getTransactionInteraction() : TransactionInteraction
	{
		$interaction = new TransactionInteraction();

		$interaction->setOrigin(self::KEY_ORIGIN);
		$interaction->setPosConditionCode(self::KEY_POS_CODE);
		$interaction->setEciIndicator(self::KEY_ECI_INDICATOR);

		return $interaction;
	}

	private function getMerchantDetails() : MerchantDetails
	{
		$merchantDetails = new MerchantDetails();

		$merchantDetails->setMerchantId($this->chConfig->getMerchantId());
		$merchantDetails->setTerminalId($this->chConfig->getTerminalId());
		$merchantDetails->setMerchantPartner(MerchantPartnerHelper::createMerchantPartner($this->chConfig));

		return $merchantDetails;
	}
}
