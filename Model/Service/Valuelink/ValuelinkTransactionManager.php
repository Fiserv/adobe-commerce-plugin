<?php

namespace Fiserv\Payments\Model\Service\Valuelink;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Model\MethodInterface;
use Fiserv\Payments\Model\Valuelink\ValuelinkQuoteRecord;
use Fiserv\Payments\Helper\Valuelink\DataHelper;
use Fiserv\Payments\Model\Adapter\Valuelink\ValuelinkChargesRequest;
use Fiserv\Payments\Model\Adapter\Valuelink\ValuelinkCancelRequest;
use Fiserv\Payments\Model\Adapter\Valuelink\ValuelinkCaptureRequest;
use Fiserv\Payments\Model\ValuelinkTransactionFactory;
use Fiserv\Payments\Model\ValuelinkTransaction;
use Fiserv\Payments\Api\Valuelink\ValuelinkTransactionRepositoryInterface;
use Fiserv\Payments\Gateway\Config\Valuelink\Config as ValuelinkConfig;
use Fiserv\Payments\Model\Service\CommerceHub\FailedTransactionManager;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;

class ValuelinkTransactionManager
{
	/**
	 *
	 * @var Json
	 */
	private $serializer;
			
	/**
	 * @var \Magento\Quote\Api\CartRepositoryInterface
	 */
	protected $quoteRepository;

	private $valuelinkTransactionRepository;

	private $logger;

	private $valuelinkDataHelper;
	
	private $valuelinkChargesAdapter;

	private $valuelinkCancelAdapter;
	
	private $valuelinkCaptureAdapter;

	private $valuelinkTransactionFactory;

	private $valuelinkConfig;

	private $_localeDate;

	private $failedTxnManager;

	/**
	 * @var string
	 */
	private $merchantOrderId;

	private $paths = [ 
			'transactionId' => ['gatewayResponse', 'transactionProcessingDetails'],
			'apiTraceId' => ['gatewayResponse', 'transactionProcessingDetails'],
			'responseMessage' => ['paymentReceipt', 'processorResponseDetails'],
			'sourceType' => ['source'],
			'merchantOrderId' => ['transactionDetails'],
			'transactionState' => ['gatewayResponse'],
			'approvalStatus' => ['paymentReceipt', 'processorResponseDetails'],
			'approvedAmount' => ['paymentReceipt', 'approvedAmount'],
			'processor' => ['paymentReceipt', 'processorResponseDetails'],
			'host' => ['paymentReceipt', 'processorResponseDetails'],
			'merchantId' => ['merchantDetails'],
			'expirationMonth' => ['source', 'card'],
			'expirationYear' => ['source', 'card'],
			'last4' => ['source', 'card'],
			'scheme' => ['source', 'card'],
			'bin' => ['source', 'card'],
			'networkResponseCode' => ['networkDetails'],
			'currency' => ['paymentReceipt', 'approvedAmount'],
			'securityCodeMatch' => ['paymentReceipt', 'processorResponseDetails', 'bankAssociationDetails', 'avsSecurityCodeResponse'],
			'bankAssociationDetails' => ['paymentReceipt', 'processorResponseDetails', 'bankAssociationDetails'],
			'hostResponseMessage' => ['paymentReceipt', 'processorResponseDetails'],
			'retrievalReferenceNumber' => ['transactionDetails'],
			'countryCode' => [],
			'responseCode' => ['paymentReceipt', 'processorResponseDetails'],
			'merchantAdviceCode' => ['networkDetails'],
			'amount' => [],
			'errorCode' => ['error', 0],
			'errorMessage' => ['error', 0],
			HttpClient::STATUS_CODE_KEY => []
	];

	public function __construct(
		Json $serializer,	
		\Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
		ValuelinkTransactionRepositoryInterface $valuelinkTransactionRepository,
		DataHelper $valuelinkDataHelper,
		ValuelinkChargesRequest $valuelinkChargesAdapter,
		ValuelinkCancelRequest $valuelinkCancelAdapter,
		ValuelinkCaptureRequest $valuelinkCaptureAdapter,
		ValuelinkTransactionFactory $valuelinkTransactionFactory,
		ValuelinkConfig $valuelinkConfig,
		\Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
		FailedTransactionManager $failedTxnManager,
		MultiLevelLogger $logger
	) {
		$this->serializer = $serializer;
		$this->quoteRepository = $quoteRepository;
		$this->valuelinkTransactionRepository = $valuelinkTransactionRepository;
		$this->valuelinkDataHelper = $valuelinkDataHelper;
		$this->valuelinkChargesAdapter = $valuelinkChargesAdapter;
		$this->valuelinkCancelAdapter = $valuelinkCancelAdapter;
		$this->valuelinkCaptureAdapter = $valuelinkCaptureAdapter;
		$this->valuelinkTransactionFactory = $valuelinkTransactionFactory;
		$this->valuelinkConfig = $valuelinkConfig;
		$this->_localeDate = $localeDate;
		$this->failedTxnManager = $failedTxnManager;
		$this->logger = $logger;
	}

	public function chargeValuelinkCard($order, ValuelinkQuoteRecord $valuelinkRecord)
	{
		$chResponse = null;
		$transactionType = null;

		try
		{
			$valuelinkTransaction = $this->valuelinkTransactionFactory->create();
			$valuelinkTransaction->setOrderIncrementId($order->getIncrementId());
			$valuelinkTransaction->setAmount($valuelinkRecord->getAmountToCharge());
			$valuelinkTransaction->setCurrency('USD');
			$valuelinkTransaction->setDateCreated(new \DateTime('now', new \DateTimeZone($this->_localeDate->getConfigTimezone())));

			$paymentAction = $this->valuelinkConfig->getPaymentAction();
			$transactionType = "UNKNOWN";
			if ($paymentAction == MethodInterface::ACTION_AUTHORIZE)
			{
				$transactionType = ValuelinkTransaction::AUTHORIZE_TYPE;
			}
			else if ($paymentAction == MethodInterface::ACTION_AUTHORIZE_CAPTURE)
			{
				$transactionType = ValuelinkTransaction::SALE_TYPE;
			}
			$valuelinkTransaction->setTransactionType($transactionType);

			// Generate or retrieve the merchantTransactionId and merchantOrderId
			$merchantOrderId = $order->getIncrementId(); // Use order increment ID or generate a unique order ID

			// Updated call to include MerchantOrderId and MerchantTransactionId
			$payload = $this->valuelinkChargesAdapter->getValuelinkChargesPayload(
				$valuelinkRecord->getSessionId(),
				$valuelinkRecord->getAmountToCharge(),
				'USD',
				$merchantOrderId
			);

			$valuelinkTransaction->setChRequest(json_encode($payload));
			$this->logger->logInfo(1, "Initiating Gift Card " . $transactionType . " Transaction", "Order ID: {$merchantOrderId}");

			$parsedChResponse = $this->valuelinkChargesAdapter->chargeValuelinkCard($payload);	
			$chResponse = $parsedChResponse[ValuelinkChargesRequest::KEY_RESPONSE];

			$statusCode = $parsedChResponse[ValuelinkChargesRequest::KEY_STATUS_CODE];
			if ($statusCode !== 201) {
				$this->logger->logError(1, "Transaction failure. Gift card response returned with unsuccessful status", "Order ID: {$merchantOrderId}");
				$this->logger->logError(2, "Status Code: " . $statusCode, "Order ID: {$merchantOrderId}");

				throw new \Exception('CommerceHub Gift Card Charges Request  HTTP error code: ' . $statusCode, 1);
			};

			$valuelinkTransaction->setChResponse(json_encode($chResponse));
			$valuelinkTransaction->setTransactionId($this->extractTransactionId($chResponse));
			$valuelinkTransaction->setTransactionState($this->extractTransactionState($chResponse));		
			
			$successStates = [ValuelinkTransaction::AUTHORIZED_STATE, ValuelinkTransaction::CAPTURED_STATE];
			if (!in_array($valuelinkTransaction->getTransactionState(), $successStates))
			{
				$this->logger->logError(1, "Transaction failure. Gift card response returned with unsuccessful transaction state", "Order ID: {$merchantOrderId}");
				$this->logger->logError(1, "Transaction ID: " . $valuelinkTransaction->getTransactionId(), "Order ID: {$merchantOrderId}");
				$this->logger->logError(2, "Transaction state: " . $valuelinkTransaction->getTransactionState(), "Order ID: {$merchantOrderId}");
				$this->logger->logError(2, "Response message: " . $this->extractResponseMessage($chResponse), "Order ID: {$merchantOrderId}");
				throw new \Exception(__("Gift Card response state not recognized as successful: " . $valuelinkTransaction->getTransactionState()));
			}
			
			$verb = $valuelinkTransaction->getTransactionState() === ValuelinkTransaction::CAPTURED_STATE ? "captured" : "authorized";

			$order->addStatusHistoryComment("Gift Card " . $verb . " amount of: $" . number_format(round($valuelinkTransaction->getAmount(),2), 2, '.', '') . ". Transaction ID: \"" . $valuelinkTransaction->getTransactionId() . "\"")
				->setIsCustomerNotified(false);

			$this->logger->logInfo(1, "Transaction success", "Order ID: {$merchantOrderId}");
			$this->logger->logInfo(1, "Transaction ID: " . $valuelinkTransaction->getTransactionId(), "Order ID: {$merchantOrderId}");
			$this->valuelinkTransactionRepository->save($valuelinkTransaction);
			return $valuelinkTransaction;
	
		} catch(\Exception $e)
		{
			if (!is_null($chResponse) && !is_null($transactionType))
			{
				$this->failedTxnManager->createFailedTransaction($merchantOrderId, $chResponse, $this->paths, $transactionType);
			}

			$this->logger->logError(1, "An error occurred while creating primary gift transaction", "Order ID: {$merchantOrderId}");
			$this->logger->logError(2, $e, "Order ID: {$merchantOrderId}");
			throw $e;
		}
	}


	public function cancelValuelinkTransaction($order, array $primaryTxn)
	{
		$chResponse = null;
		
		try
		{
			$merchantOrderId = $order->getIncrementId();
			$this->logger->logInfo(1, "Initiating Gift Card Cancel Transaction", "Order ID: {$merchantOrderId}");
			
			$cancelTxn = $this->valuelinkTransactionFactory->create();
			$cancelTxn->setParentTransactionId($primaryTxn["entity_id"]);
			$cancelTxn->setOrderId($primaryTxn["order_id"]);
			$cancelTxn->setOrderIncrementId($primaryTxn["order_increment_id"]);
			$cancelTxn->setAmount($primaryTxn["amount"]);
			$cancelTxn->setCurrency($primaryTxn["currency"]);
			$cancelTxn->setDateCreated(new \DateTime('now', new \DateTimeZone($this->_localeDate->getConfigTimezone())));
			$cancelTxn->setTransactionType(ValuelinkTransaction::CANCEL_TYPE);

			$merchantTxnId = $this->getMerchantTransactionId($primaryTxn);
			$merchantOrderId = $order->getIncrementId();

			$payload = $this->valuelinkCancelAdapter->getValuelinkCancelPayload(
				$primaryTxn["transaction_id"],
				$merchantTxnId,
				$merchantOrderId);

			$cancelTxn->setChRequest(json_encode($payload));

			$parsedChResponse = $this->valuelinkCancelAdapter->cancelValuelinkRequest($payload);
			$chResponse = $parsedChResponse[ValuelinkCancelRequest::KEY_RESPONSE];

			$statusCode = $parsedChResponse[ValuelinkCancelRequest::KEY_STATUS_CODE];
			if ($statusCode !== 201) {
				$this->logger->logError(1, "Transaction failure. Gift card response returned with unsuccessful status", "Order ID: {$merchantOrderId}");
				$this->logger->logError(2, "Status Code: " . $statusCode, "Order ID: {$merchantOrderId}");

				throw new \Exception('CommerceHub Gift Card Charges Request  HTTP error code: ' . $statusCode, 1);
			};

			$cancelTxn->setChResponse(json_encode($response));
			$cancelTxn->setTransactionId($this->extractTransactionId($response));
			$cancelTxn->setTransactionState($this->extractTransactionState($response));
		
			$successStates = [ValuelinkTransaction::VOIDED_STATE];
			if (!in_array($cancelTxn->getTransactionState(), $successStates))
			{
				$this->logger->logError(1, "Transaction failure. Gift card response returned with unsuccessful transaction state", "Order ID: {$merchantOrderId}");
				$this->logger->logError(1, "Transaction ID: " . $cancelTxn->getTransactionId(), "Order ID: {$merchantOrderId}");
				$this->logger->logError(2, "Transaction state: " . $cancelTxn->getTransactionState(), "Order ID: {$merchantOrderId}");
				$this->logger->logError(2, "Response message: " . $this->extractResponseMessage($response), "Order ID: {$merchantOrderId}");
				throw new \Exception(__("Gift Card response state not recognized as successful: " . $cancelTxn->getTransactionState()));
			}
	
			$order->addStatusHistoryComment("Gift Card voided amount of: $" . number_format(round($cancelTxn->getAmount(),2), 2, '.', '') . ". Transaction ID: \"" . $cancelTxn->getTransactionId() . "\"")
				->setIsCustomerNotified(false);

			$this->logger->logInfo(1, "Transaction success", "Order ID: {$order->getIncrementId()}");
			$this->logger->logInfo(1, "Transaction ID: " . $cancelTxn->getTransactionId(), "Order ID: {$merchantOrderId}");
			$this->valuelinkTransactionRepository->save($cancelTxn);
			return $cancelTxn;
		
		} catch(\Exception $e)
		{
			if (!is_null($chResponse))
			{
				$this->failedTxnManager->createFailedTransaction($merchantOrderId, $chResponse, $this->paths, "CANCEL");
			}

			$this->logger->logError(1, "An error occurred while voiding gift transaction", "Order ID: {$merchantOrderId}");
			$this->logger->logError(2, $e, "Order ID: {$merchantOrderId}");
			throw $e;
		}
	}

	public function captureValuelinkTransaction($invoice, $authToCapture, $amtToCapture, $previousCaptures, $finalCapture)
	{
		$chResponse = null;

		try
		{
			$order = $invoice->getOrder();
			$merchantOrderId = $order->getIncrementId();
			$this->logger->logInfo(1, "Initiating Gift Card Capture Transaction", "Order ID: {$merchantOrderId}");
			
			// String is sometimes passed instead of float:
			$amtToCapture = round(floatval($amtToCapture),2);

			$captureTxn = $this->valuelinkTransactionFactory->create();
			$captureTxn->setParentTransactionId($authToCapture["entity_id"]);
			$captureTxn->setOrderId($authToCapture["order_id"]);
			$captureTxn->setOrderIncrementId($authToCapture["order_increment_id"]);
			$captureTxn->setAmount($amtToCapture);
			$captureTxn->setCurrency($authToCapture["currency"]);
			$captureTxn->setDateCreated(new \DateTime('now', new \DateTimeZone($this->_localeDate->getConfigTimezone())));
			$captureTxn->setTransactionType(ValuelinkTransaction::CAPTURE_TYPE);

			$payload = $this->valuelinkCaptureAdapter->getValuelinkCapturePayload(
				$authToCapture["transaction_id"],
				$amtToCapture,
				$previousCaptures,
				$finalCapture,
				$captureTxn->getCurrency(),
				$merchantOrderId);

			$captureTxn->setChRequest(json_encode($payload));

			$parsedChResponse = $this->valuelinkCaptureAdapter->captureValuelinkRequest($payload);
			$chResponse = $parsedChResponse[ValuelinkCaptureRequest::KEY_RESPONSE];

			$statusCode = $parsedChResponse[ValuelinkCaptureRequest::KEY_STATUS_CODE];
			if ($statusCode !== 201) {
				$this->logger->logError(1, "Transaction failure. Gift card response returned with unsuccessful status", "Order ID: {$merchantOrderId}");
				$this->logger->logError(2, "Status Code: " . $statusCode, "Order ID: {$merchantOrderId}");

				throw new \Exception('CommerceHub Gift Card Charges Request  HTTP error code: ' . $statusCode, 1);
			};

			$captureTxn->setChResponse(json_encode($response));
			$captureTxn->setTransactionId($this->extractTransactionId($response));
			$captureTxn->setTransactionState($this->extractTransactionState($response));	
			
			$successStates = [ValuelinkTransaction::CAPTURED_STATE];
			if (!in_array($captureTxn->getTransactionState(), $successStates))
			{
				$this->logger->logError(1, "Transaction failure. Gift card response returned with unsuccessful transaction state", "Order ID: {$merchantOrderId}");
				$this->logger->logError(1, "Transaction ID: " . $captureTxn->getTransactionId(), "Order ID: {$merchantOrderId}");
				$this->logger->logError(2, "Transaction state: " . $captureTxn->getTransactionState(), "Order ID: {$merchantOrderId}");
				$this->logger->logError(2, "Response message: " . $this->extractResponseMessage($response), "Order ID: {$merchantOrderId}");
				throw new \Exception(__("Gift Card response state not recognized as successful: " . $captureTxn->getTransactionState()));
			}
	
			$order->addStatusHistoryComment("Gift Card captured amount of: $" . number_format(round($captureTxn->getAmount(),2), 2, '.', '') . ". Transaction ID: \"" . $captureTxn->getTransactionId() . "\"")
				->setIsCustomerNotified(false);

			$this->logger->logInfo(1, "Transaction success");
			$this->logger->logInfo(1, "Transaction ID: " . $captureTxn->getTransactionId());
			$this->valuelinkTransactionRepository->save($captureTxn);
			return $captureTxn;
		
		} catch(\Exception $e)
		{
			if (!is_null($chResponse))
			{
				$this->failedTxnManager->createFailedTransaction($merchantOrderId, $chResponse, $this->paths, "CAPTURE");
			}
	
			$this->logger->logError(1, "An error occurred while capturing gift transaction", "Order ID: {$merchantOrderId}");
			$this->logger->logError(2, $e, "Order ID: {$merchantOrderId}");
			throw $e;
		}
	}

	private function getMerchantTransactionId($transaction)
	{
		$rawRequest = $transaction["ch_request"];
		$request = json_decode($rawRequest, true);

		return $request['transactionDetails']['merchantTransactionId'];
	}

	private function extractTransactionId($chResponse)
	{
		if (
			isset($chResponse["gatewayResponse"]) && 
			isset($chResponse["gatewayResponse"]["transactionProcessingDetails"]) && 
			isset($chResponse["gatewayResponse"]["transactionProcessingDetails"]["transactionId"])
		)
		{
			return $chResponse["gatewayResponse"]["transactionProcessingDetails"]["transactionId"];
		}		
	}

	private function extractTransactionState($chResponse)
	{
		if (
			isset($chResponse["gatewayResponse"]) && 
			isset($chResponse["gatewayResponse"]["transactionState"])
		)
		{
			return $chResponse["gatewayResponse"]["transactionState"];
		}	
	}

	private function extractResponseMessage($chResponse)
	{
		return (isset($chResponse["paymentReceipt"]) &&
				isset($chResponse["paymentReceipt"]["processorResponseDetails"]) &&
				isset($chResponse["paymentReceipt"]["processorResponseDetails"]["responseMessage"])) ?
			$chResponse["paymentReceipt"]["processorResponseDetails"]["responseMessage"] : "Response message unavailable";
	}
}
