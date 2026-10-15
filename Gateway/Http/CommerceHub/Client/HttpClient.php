<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Http\CommerceHub\Client;

use Fiserv\Payments\Lib\Version;
use Fiserv\Payments\Gateway\Request\CommerceHub\Composite\ChCompositeBase;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpResponse;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Model\Method\Logger as PaymentLogger;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\AffirmVirtualCard\SessionRegistry;
use Fiserv\Payments\Model\AffirmVirtualCard\ResponseFilter;

/**
 * A client that send transaction requests to the Fiserv-CommerceHub API
 */
class HttpClient implements ClientInterface
{
	const STATUS_CODE_KEY = "statusCode";
	const RESPONSE_KEY = "response";

	/**
	 * @var PaymentLogger
	 */
	private $paymentLogger;

	/**
	 * @var ChHttpAdapter
	 */
	private $httpAdapter;

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @var SessionRegistry
	 */
	private $virtualCardSessions;

	/**
	 * @var ResponseFilter
	 */
	private $virtualCardResponseFilter;

	/**
	 * @param PaymentLogger $paymentLogger
	 * @param ChHttpAdapter $httpAdapter
	 * @param MultiLevelLogger $logger
	 * @param SessionRegistry $virtualCardSessions
	 * @param ResponseFilter $virtualCardResponseFilter
	 */
	public function __construct(
		PaymentLogger $paymentLogger,
		ChHttpAdapter $httpAdapter,
		MultiLevelLogger $logger,
		SessionRegistry $virtualCardSessions,
		ResponseFilter $virtualCardResponseFilter
	) {
		$this->paymentLogger = $paymentLogger;
		$this->httpAdapter = $httpAdapter;
		$this->logger = $logger;
		$this->virtualCardSessions = $virtualCardSessions;
		$this->virtualCardResponseFilter = $virtualCardResponseFilter;
	}

	/**
	 * Places request to gateway. Returns result as ChHttpResponse object
	 *
	 * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
	 * @return Fiserv\Payments\Lib\CommerceHub\BpResponse
	 * @throws \Magento\Payment\Gateway\Http\ClientException
	 */
	public function placeRequest(\Magento\Payment\Gateway\Http\TransferInterface $transferObject)
	{
		$requestBody = $transferObject->getBody();
		$payload = $requestBody[ChCompositeBase::REQUEST_KEY];
		$endpoint = $requestBody[ChCompositeBase::ENDPOINT_KEY];
		$orderIncrementId = $requestBody[ChCompositeBase::ORDER_INCREMENT_ID_KEY] ?? '';
		$this->logger->logInfo(1, 'Sending request to Commerce Hub', "Order ID: $orderIncrementId");
		$this->logger->logDebug(3, 'TXN REQUEST INFO', "Order ID: $orderIncrementId");
		$isVirtualCard = $this->virtualCardSessions->isVirtualCardRequest($requestBody);
		$log = ['request' => $isVirtualCard ? '[VCN payload omitted]' : $requestBody];
		if (!$isVirtualCard) {
			$this->logger->logDebug(3, "Payload:\n" . json_encode($payload, JSON_PRETTY_PRINT), "Order ID: $orderIncrementId");
		}

		try {
			$chResponse = $this->httpAdapter->sendRequest($payload, $endpoint);
			$responseArray = json_decode($chResponse->getBody(), true);
			if (!is_array($responseArray)) {
				throw new ClientException(__('Invalid payment gateway response.'));
			}
			if ($isVirtualCard) {
				$responseArray = $this->virtualCardResponseFilter->filter($responseArray);
				$this->virtualCardSessions->recordOutcome($requestBody, $responseArray);
			}
			$this->logger->logInfo(1, "Response received from Commerce Hub", "Order ID: $orderIncrementId");
			$this->logger->logDebug(3, "TXN RESPONSE INFO", "Order ID: $orderIncrementId");
			if (!$isVirtualCard) {
				$this->logger->logDebug(3, "Response Headers:\n" . json_encode($chResponse->getHeaders()), "Order ID: $orderIncrementId");
				$this->logger->logDebug(3, "Response Body:\n" . json_encode($responseArray, JSON_PRETTY_PRINT), "Order ID: $orderIncrementId");
			}
			$log['response'] = $isVirtualCard ? '[VCN response omitted]' : $chResponse->getResponse();
			return [
				self::STATUS_CODE_KEY => $chResponse->getStatusCode(),
				self::RESPONSE_KEY => $responseArray
			];
		} catch (\Exception $e) {
			$this->logger->logCritical(1, "An error has occurred while sending the payload to Commerce Hub", "Order ID: $orderIncrementId");
			if (!$isVirtualCard) {
				$this->logger->logCritical(2, $e, "Order ID: $orderIncrementId");
			}
			throw new ClientException(
				__('An error occurred in the payment gateway.')
			);
		} finally {
			$this->paymentLogger->debug($log);
		}
	}
}
