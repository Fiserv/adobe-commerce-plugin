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
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger as PaymentLogger;
use Fiserv\Payments\Logger\MultiLevelLogger;

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
	 * @param PaymentLogger $paymentLogger
	 * @param MultiLevelLogger $logger
	 * @param ChHttpAdapter $httpAdapter
	 */
	public function __construct(
		PaymentLogger $paymentLogger,
		ChHttpAdapter $httpAdapter,
		MultiLevelLogger $logger
	) {
		$this->paymentLogger = $paymentLogger;
		$this->httpAdapter = $httpAdapter;
		$this->logger = $logger;
	}

	/**
	 * Places request to gateway. Returns result as ChHttpResponse object
	 *
	 * @param TransferInterface $transferObject
	 * @return Fiserv\Payments\Lib\CommerceHub\BpResponse
	 * @throws \Magento\Payment\Gateway\Http\ClientException
	 */
	public function placeRequest(TransferInterface $transferObject) 
	{
		$requestBody = $transferObject->getBody();

		$payload = $requestBody[ChCompositeBase::REQUEST_KEY];
		$endpoint = $requestBody[ChCompositeBase::ENDPOINT_KEY];
		
		$log = [
			'request' => $requestBody,
		];

		$this->logger->logInfo(1, "Sending request to Commerce Hub");
		$this->logger->logInfo(3, "TXN REQUEST INFO");
		$this->logger->logInfo(3, "Payload:\n" . print_r($payload, true));
		
		try {
			$chResponse = $this->httpAdapter->sendRequest($payload, $endpoint);
			$this->logger->logInfo(1, "Response received from Commerce Hub");
			$this->logger->logInfo(3, "TXN RESPONSE INFO");
			$this->logger->logInfo(3, "Response Headers:\n" . print_r($chResponse->getHeaders(), true));
			$this->logger->logInfo(3, "Response Body:\n" . json_encode(json_decode($chResponse->getBody()), JSON_PRETTY_PRINT));
			
			$log['response'] = $chResponse->getResponse();
			return [
				self::STATUS_CODE_KEY => $chResponse->getStatusCode(),
				self::RESPONSE_KEY => json_decode($chResponse->getBody(), true)
			];
		} catch (\Exception $e) {
			$this->logger->logCritical(1, "An error has occurred while sending the payload to Commerce Hub");
			$this->logger->logCritical(2, $e);
			throw new ClientException(
				__('An error occurred in the payment gateway.')
			);
		} finally {
			$this->paymentLogger->debug($log);
		}
	}
}
