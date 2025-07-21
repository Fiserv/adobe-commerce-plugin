<?php
namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Validator\CommerceHub\TransactionResponseValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Service\CommerceHub\FailedTransactionManager;
use Fiserv\Payments\Model\Adapter\CommerceHub\CancelsRequest;

/**
 * Validates the status of an attempted Auth transaction
 */
class AuthorizeResponseValidator extends TransactionResponseValidator
{
	const PIN_ONLY = "PIN_ONLY";
	const MERCHANT_DETAILS_KEY = "merchantDetails";
	const PIN_ONLY_ERROR = "PIN-ONLY-AUTH-ERROR";
	const PAYMENT_ACTION = "AUTHORIZATION";

	private $cancelsAdapter;

	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 * @param MultiLevelLogger $logger
	 * @param FailedTransactionManager $failedTransactionManager
	 */
	public function __construct(
		CancelsRequest $cancelsAdapter,
		ResultInterfaceFactory $resultFactory, 
		SubjectReader $subjectReader, 
		MultiLevelLogger $logger, 
		FailedTransactionManager $failedTransactionManager
	) {
		parent::__construct(
			$resultFactory,
			$subjectReader, 
			$logger, 
			$failedTransactionManager
		);
		$this->cancelsAdapter = $cancelsAdapter;
		array_push($this->successStates, self::STATE_AUTHORIZED);
	}

	public function validate(array $validationSubject): ResultInterface
	{
		$chRawResponse = $this->subjectReader->readChResponseFromResponse($validationSubject);
		$errorMessages = [];
		$errorCodes = [];

		// Define the paths for values to be extracted
		$paths = [
			'transactionId' => [HttpClient::RESPONSE_KEY, 'gatewayResponse', 'transactionProcessingDetails'],
			'apiTraceId' => [HttpClient::RESPONSE_KEY, 'gatewayResponse', 'transactionProcessingDetails'],
			'responseMessage' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
			'sourceType' => [HttpClient::RESPONSE_KEY, 'source'],
			'merchantOrderId' => [HttpClient::RESPONSE_KEY, 'transactionDetails'],
			'transactionState' => [HttpClient::RESPONSE_KEY, 'gatewayResponse'],
			'approvalStatus' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
			'approvedAmount' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'approvedAmount'],
			'processor' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
			'host' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
			'merchantId' => [HttpClient::RESPONSE_KEY, 'merchantDetails'],
			'expirationMonth' => [HttpClient::RESPONSE_KEY, 'source', 'card'],
			'expirationYear' => [HttpClient::RESPONSE_KEY, 'source', 'card'],
			'last4' => [HttpClient::RESPONSE_KEY, 'source', 'card'],
			'scheme' => [HttpClient::RESPONSE_KEY, 'source', 'card'],
			'bin' => [HttpClient::RESPONSE_KEY, 'source', 'card'],
			'networkResponseCode' => [HttpClient::RESPONSE_KEY, 'networkDetails'],
			'currency' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'approvedAmount'],
			'bankAssociationDetails' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails', 'bankAssociationDetails'],
			'securityCodeMatch' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails', 'bankAssociationDetails', 'avsSecurityCodeResponse'],
			'errorCode' => [HttpClient::RESPONSE_KEY, 'error', 0],
			'errorMessage' => [HttpClient::RESPONSE_KEY, 'error', 0],
			'retrievalReferenceNumber' => [HttpClient::RESPONSE_KEY, 'transactionDetails'],
			'responseCode' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
			'amount' => [HttpClient::RESPONSE_KEY],
			'detailedCardProduct' => [HttpClient::RESPONSE_KEY, 'cardDetails'],
			'hostResponseMessage' => [HttpClient::RESPONSE_KEY, 'paymentReceipt', 'processorResponseDetails'],
			'countryCode' => [HttpClient::RESPONSE_KEY],
			'merchantAdviceCode' => [HttpClient::RESPONSE_KEY, 'networkDetails'],
			self::MERCHANT_DETAILS_KEY => [HttpClient::RESPONSE_KEY],
			HttpClient::STATUS_CODE_KEY => []
		];

		// Extract order ID from the validation subject
		$order = $this->subjectReader->readPayment($validationSubject)->getOrder();
		$orderIncrementId = $order->getOrderIncrementId();
		$countryCode = $order->getBillingAddress()->getCountryId();

		// Verify Status Code
		$statusCode = $this->subjectReader->getValueSafely($chRawResponse, HttpClient::STATUS_CODE_KEY, $paths[HttpClient::STATUS_CODE_KEY]);
		if (!$this->isStatusSuccessful($statusCode)) {
			array_push($errorMessages, "Something went wrong while processing CommerceHub transaction.");
			array_push($errorCodes, $statusCode);
			$this->logger->logError(2, "Transaction failure. Commerce Hub response returned with unsuccessful status", "Order ID: " . ($orderIncrementId ?? "Not found"));
			$this->logger->logError(2, "Status Code: " . $statusCode, "Order ID: " . ($orderIncrementId ?? "Not found"));
			
			$this->failedTransactionManager->createFailedTransaction($orderIncrementId, $chRawResponse, $paths, $this->getPaymentAction());
			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		// Extracting data from Response
		$pinOnlyState = $this->subjectReader->getValueSafely($chRawResponse, 'detailedCardProduct', $paths['detailedCardProduct']);
		$orderIncrementId = $this->subjectReader->getValueSafely($chRawResponse, 'merchantOrderId', $paths['merchantOrderId']);

		// Check for PIN only Condition
		if ($pinOnlyState === self::PIN_ONLY) {
			array_push($errorMessages, self::PIN_ONLY_ERROR);
			array_push($errorCodes, $pinOnlyState);
			
			$transactionId = $this->subjectReader->getValueSafely($chRawResponse, 'transactionId', $paths['transactionId']);
			$this->logger->logError(2, "Invalid transaction processed for online payment. Canceling...", "Order ID: $orderIncrementId");
			$this->logger->logError(2, "Invalid transaction ID: " . $transactionId, "Order ID: $orderIncrementId");
			$this->logger->logError(2, "Invalid reason: " . $pinOnlyState, "Order ID: $orderIncrementId");
			$this->logger->logError(2, "Canceling transaction", "Order ID: $orderIncrementId");

			try
			{	
				// Send Cancel Request
				$cancelResponse = $this->cancelsAdapter->requestCancel($transactionId);
				if (isset($cancelResponse["gatewayResponse"]["transactionProcessingDetails"]["transactionId"])) {
					$this->logger->logError(2, "Cancel Transaction ID: " . $cancelResponse["gatewayResponse"]["transactionProcessingDetails"]["transactionId"], "Order ID: $orderIncrementId");
					$this->logger->logDebug(3, "Cancel Response: " . json_encode($cancelResponse, JSON_PRETTY_PRINT));
				}

				$chRawResponse[HttpClient::RESPONSE_KEY]['countryCode'] = $countryCode;
				$chRawResponse[HttpClient::RESPONSE_KEY]['paymentReceipt']['processorResponseDetails']['approvalStatus'] = $chRawResponse[HttpClient::RESPONSE_KEY]['paymentReceipt']['processorResponseDetails']['approvalStatus'] . " (PIN_ONLY CANCEL)";
				$cancelResponse[HttpClient::RESPONSE_KEY]['countryCode'] = $countryCode;
				$cancelResponse['paymentReceipt']['processorResponseDetails']['approvalStatus'] = $cancelResponse['paymentReceipt']['processorResponseDetails']['approvalStatus'] . " (PIN_ONLY CANCEL)";
				$cancelResponseFormatted = array();
				$cancelResponseFormatted[HttpClient::RESPONSE_KEY] = $cancelResponse;
				$this->failedTransactionManager->createFailedTransaction($orderIncrementId, $chRawResponse, $this->paths, $this->getPaymentAction());
				$this->failedTransactionManager->createFailedTransaction($orderIncrementId, $cancelResponseFormatted, $this->paths, $this->getPaymentAction());
			} catch (\Exception $e) {	
				$this->logger->logEmergency(1, "An error occurred while canceling PIN-ONLY authorization: " . $e->getMessage(), "Order ID: $orderIncrementId");
				throw $e;
			}
			return $this->createResult(false, $errorMessages, $errorCodes);
		}

		// Call parent for additional validation if needed
		$parentResult = parent::validate($validationSubject);
		if (!$parentResult->isValid()) {
			return $parentResult;
		}
		return $this->createResult(true);
	}

	protected function getPaymentAction()
	{
		return self::PAYMENT_ACTION;
	}
}
