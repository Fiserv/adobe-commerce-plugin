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

use Fiserv\Payments\Lib\CommerceHub\Model\CancelRequest;
use Fiserv\Payments\Lib\CommerceHub\Model\ReferenceTransactionDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails;

class ValuelinkCancelRequest
{
	// Cancel endpoint on dev portal is incorrect	
	//const CANCEL_ENDPOINT = 'payments-vas/v1/accounts/gift-cards';
	const CANCEL_ENDPOINT = 'payments/v1/cancels';
	const KEY_OPERATION_TYPE = "CANCEL";

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
	public function cancelValuelinkRequest(CancelRequest $payload)
	{
		$this->logger->logInfo(1, "Sending gift card request...");
		$this->logger->logInfo(3, "TXN REQUEST INFO");
		$this->logger->logInfo(3, "Payload:\n" . print_r($payload, true));
		$chResponse = $this->httpAdapter->sendRequest($payload, self::CANCEL_ENDPOINT);
		return $this->parseCancelResponse($chResponse);
	}

	private function parseCancelResponse($chResponse) {
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
			throw new \Exception('CommerceHub Valuelink Cancel Request HTTP error code: ' . $statusCode, 1);
		};

		return $bodyArray;
	}

	public function getValuelinkCancelPayload($txnId, $merchantTxnId) : CancelRequest
	{
		$payload = new CancelRequest();
		
		$payload->setReferenceTransactionDetails($this->getReferenceTransactionDetails($txnId, $merchantTxnId));
		$payload->setTransactionDetails($this->getTransactionDetails());
		$payload->setTransactionInteraction($this->getTransactionInteraction());
		$payload->setMerchantDetails($this->getMerchantDetails());
		
		return $payload;
	}

	private function getReferenceTransactionDetails($txnId, $merchantTxnId) : ReferenceTransactionDetails
	{
		$refTxn = new  ReferenceTransactionDetails();

		$refTxn->setReferenceTransactionId($txnId);
		
		// Commerce Hub guidance is to provide only one identifier
		//$refTxn->setReferenceMerchantTransactionId($merchantTxnId);

		return $refTxn;
	}
	
	private function getTransactionDetails() : TransactionDetails
	{
		$details = new TransactionDetails();
                $details->setMerchantTransactionId(uniqid());
		$details->setMerchantOrderId(uniqid());	
		$details->setOperationType(self::KEY_OPERATION_TYPE);

		return $details;
	}

	private function getTransactionInteraction() : TransactionInteraction
	{
		$interaction = new TransactionInteraction();

		$date = new \DateTime();	
		$interaction->setTerminalTimestamp($date->format('c'));

		return $interaction;
	}

	private function getMerchantDetails() : MerchantDetails
	{
		$merchantDetails = new MerchantDetails();

		$merchantDetails->setMerchantId($this->chConfig->getMerchantId());
		$merchantDetails->setTerminalId($this->chConfig->getTerminalId());
		
		return $merchantDetails;
	}
}
