<?php

namespace Fiserv\Payments\Model\Service\Valuelink;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Fiserv\Payments\Model\Valuelink\ValuelinkQuoteRecord;
use Fiserv\Payments\Helper\Valuelink\DataHelper;
use Fiserv\Payments\Model\Adapter\Valuelink\ValuelinkChargesRequest;
use Fiserv\Payments\Model\Adapter\Valuelink\ValuelinkCancelRequest;
use Fiserv\Payments\Model\Adapter\Valuelink\ValuelinkCaptureRequest;
use Fiserv\Payments\Model\ValuelinkTransactionFactory;
use Fiserv\Payments\Model\ValuelinkTransaction;
use Fiserv\Payments\Api\Valuelink\ValuelinkTransactionRepositoryInterface;
use Fiserv\Payments\Gateway\Config\Valuelink\Config as ValuelinkConfig;

use Fiserv\Payments\Logger\MultiLevelLogger;


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
		$this->logger = $logger;
	}

	public function chargeValuelinkCard($order, ValuelinkQuoteRecord $valuelinkRecord)
	{
		try
		{
			$valuelinkTransaction = $this->valuelinkTransactionFactory->create();
			$valuelinkTransaction->setOrderIncrementId($order->getIncrementId());

			$valuelinkTransaction->setAmount($valuelinkRecord->getAmountToCharge());
			$valuelinkTransaction->setCurrency('USD');
			$valuelinkTransaction->setDateCreated(new \DateTime('now', new \DateTimeZone($this->_localeDate->getConfigTimezone())));
			$valuelinkTransaction->setTransactionType($this->valuelinkConfig->getPaymentAction());

			$payload = $this->valuelinkChargesAdapter->getValuelinkChargesPayload($valuelinkRecord->getSessionId(), $valuelinkRecord->getAmountToCharge(), 'USD');
			//$payload = $this->valuelinkChargesAdapter->getValuelinkChargesPayload($valuelinkRecord->getSessionId(), 1.00, 'USD');
			$valuelinkTransaction->setChRequest(json_encode($payload));

			if($payload->getTransactionDetails()->getCaptureFlag()) {
				$this->logger->logInfo(1, "Initiating Gift Card Sale Transaction");
			} else {
				$this->logger->logInfo(1, "Initiating Gift Card Auth Transaction");
			}

			$chResponse = $this->valuelinkChargesAdapter->chargeValuelinkCard($payload);	

			$valuelinkTransaction->setChResponse(json_encode($chResponse));
			$valuelinkTransaction->setTransactionId($this->extractTransactionId($chResponse));
			$valuelinkTransaction->setTransactionState($this->extractTransactionState($chResponse));	

			$successStates = [ValuelinkTransaction::AUTHORIZED_STATE, ValuelinkTransaction::CAPTURED_STATE];
			if (!in_array($valuelinkTransaction->getTransactionState(), $successStates))
			{
				$this->logger->logError(1, "Transaction failure. Gift card response returned with unsuccessful transaction state");
				$this->logger->logError(1, "Transaction ID: " . $valuelinkTransaction->getTransactionId());
				$this->logger->logError(2, "Transaction state: " . $valuelinkTransaction->getTransactionState());
				$this->logger->logError(2, "Response message: " . $this->extractResponseMessage($chResponse));
				throw new \Exception(__("Valuelink response state not recognized as successful: " . $valuelinkTransaction->getTransactionState()));
			}
			
			$verb = $valuelinkTransaction->getTransactionState() === ValuelinkTransaction::CAPTURED_STATE ? "captured" : "authorized";

			$order->addStatusHistoryComment("Valuelink gift card " . $verb . " amount of: $" . number_format(round($valuelinkTransaction->getAmount(),2), 2, '.', '') . ". Transaction ID: \"" . $valuelinkTransaction->getTransactionId() . "\"")
				->setIsCustomerNotified(false);

			$this->logger->logInfo(1, "Transaction success");
			$this->logger->logInfo(1, "Transaction ID: " . $valuelinkTransaction->getTransactionId());
			$this->valuelinkTransactionRepository->save($valuelinkTransaction);
			return $valuelinkTransaction;
	
		} catch(\Exception $e)
		{
			$this->logger->logError(1, "An error occurred while redeeming Valuelink card");
			$this->logger->logError(2, $e);
			throw $e;
		}
	}

	public function cancelValuelinkTransaction($order, array $primaryTxn)
	{
		try
		{
			$this->logger->logInfo(1, "Initiating Gift Card Cancel Transaction");
			
			$cancelTxn = $this->valuelinkTransactionFactory->create();
			$cancelTxn->setParentTransactionId($primaryTxn["entity_id"]);
			$cancelTxn->setOrderId($primaryTxn["order_id"]);
			$cancelTxn->setOrderIncrementId($primaryTxn["order_increment_id"]);
			$cancelTxn->setAmount($primaryTxn["amount"]);
			$cancelTxn->setCurrency($primaryTxn["currency"]);
			$cancelTxn->setDateCreated(new \DateTime('now', new \DateTimeZone($this->_localeDate->getConfigTimezone())));

			$merchantTxnId = $this->getMerchantTransactionId($primaryTxn);
			$payload = $this->valuelinkCancelAdapter->getValuelinkCancelPayload($primaryTxn["transaction_id"], $merchantTxnId);

			$cancelTxn->setChRequest(json_encode($payload));

			$response = $this->valuelinkCancelAdapter->cancelValuelinkRequest($payload);

			$cancelTxn->setChResponse(json_encode($response));
			$cancelTxn->setTransactionId($this->extractTransactionId($response));
			$cancelTxn->setTransactionState($this->extractTransactionState($response));
		
			$successStates = [ValuelinkTransaction::VOIDED_STATE];
			if (!in_array($cancelTxn->getTransactionState(), $successStates))
			{
				$this->logger->logError(1, "Transaction failure. Gift card response returned with unsuccessful transaction state");
				$this->logger->logError(1, "Transaction ID: " . $cancelTxn->getTransactionId());
				$this->logger->logError(2, "Transaction state: " . $cancelTxn->getTransactionState());
				$this->logger->logError(2, "Response message: " . $this->extractResponseMessage($response));
				throw new \Exception(__("Valuelink response state not recognized as successful: " . $cancelTxn->getTransactionState()));
			}
	
			$order->addStatusHistoryComment("Valuelink gift card voided amount of: $" . number_format(round($cancelTxn->getAmount(),2), 2, '.', '') . ". Transaction ID: \"" . $cancelTxn->getTransactionId() . "\"")
				->setIsCustomerNotified(false);

			$this->logger->logInfo(1, "Transaction success");
			$this->logger->logInfo(1, "Transaction ID: " . $cancelTxn->getTransactionId());
			$this->valuelinkTransactionRepository->save($cancelTxn);
			return $cancelTxn;
		
		} catch(\Exception $e)
		{
			$this->logger->logError(1, "An error occurred while Voiding Valuelink transaction");
			$this->logger->logError(2, $e);
			throw $e;
		}
	}

	public function captureValuelinkTransaction($invoice, $authToCapture, $amtToCapture, $previousCaptures, $finalCapture)
	{
		try
		{
			$this->logger->logInfo(1, "Initiating Gift Card Capture Transaction");
			
			$order = $invoice->getOrder();

			// String is sometimes passed instead of float:
			$amtToCapture = round(floatval($amtToCapture),2);

			$captureTxn = $this->valuelinkTransactionFactory->create();
			$captureTxn->setParentTransactionId($authToCapture["entity_id"]);
			$captureTxn->setOrderId($authToCapture["order_id"]);
			$captureTxn->setOrderIncrementId($authToCapture["order_increment_id"]);
			$captureTxn->setAmount($amtToCapture);
			$captureTxn->setCurrency($authToCapture["currency"]);
			$captureTxn->setDateCreated(new \DateTime('now', new \DateTimeZone($this->_localeDate->getConfigTimezone())));

			$payload = $this->valuelinkCaptureAdapter->getValuelinkCapturePayload($authToCapture["transaction_id"], $amtToCapture, $previousCaptures, $finalCapture, $captureTxn->getCurrency());

			$captureTxn->setChRequest(json_encode($payload));

			$response = $this->valuelinkCaptureAdapter->captureValuelinkRequest($payload);

			$captureTxn->setChResponse(json_encode($response));
			$captureTxn->setTransactionId($this->extractTransactionId($response));
			$captureTxn->setTransactionState($this->extractTransactionState($response));	
			
			$successStates = [ValuelinkTransaction::CAPTURED_STATE];
			if (!in_array($captureTxn->getTransactionState(), $successStates))
			{
				$this->logger->logError(1, "Transaction failure. Gift card response returned with unsuccessful transaction state");
				$this->logger->logError(1, "Transaction ID: " . $captureTxn->getTransactionId());
				$this->logger->logError(2, "Transaction state: " . $captureTxn->getTransactionState());
				$this->logger->logError(2, "Response message: " . $this->extractResponseMessage($response));
				throw new \Exception(__("Valuelink response state not recognized as successful: " . $captureTxn->getTransactionState()));
			}
	
			$order->addStatusHistoryComment("Valuelink gift card captured amount of: $" . number_format(round($captureTxn->getAmount(),2), 2, '.', '') . ". Transaction ID: \"" . $captureTxn->getTransactionId() . "\"")
				->setIsCustomerNotified(false);

			$this->logger->logInfo(1, "Transaction success");
			$this->logger->logInfo(1, "Transaction ID: " . $captureTxn->getTransactionId());
			$this->valuelinkTransactionRepository->save($captureTxn);
			return $captureTxn;
		
		} catch(\Exception $e)
		{
			$this->logger->logError(1, "An error occurred while capturing Valuelink transaction" );
			$this->logger->logError(2, $e);
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
