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
use Psr\Log\LoggerInterface;

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
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param PaymentLogger $paymentLogger
	 * @param LoggerInterface $logger
	 * @param ChHttpAdapter $httpAdapter
	 */
	public function __construct(
		PaymentLogger $paymentLogger,
		ChHttpAdapter $httpAdapter,
		LoggerInterface $logger
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

		try {
			$chResponse = $this->httpAdapter->sendRequest($payload, $endpoint);
			$this->logger->info(json_encode($payload));
			$this->logger->info(json_encode($chResponse->getResponse()));
			$log['response'] = $chResponse->getResponse();
			return [
				self::STATUS_CODE_KEY => $chResponse->getStatusCode(),
				self::RESPONSE_KEY => json_decode($chResponse->getBody(), true)
			];
		} catch (\Exception $e) {

			$this->logger->critical($e);
			throw new ClientException(
				__('An error occurred in the payment gateway.')
			);
		} finally {
			$this->paymentLogger->debug($log);
		}
	}
}
