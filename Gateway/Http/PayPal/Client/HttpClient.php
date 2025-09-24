<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Http\PayPal\Client;

use Fiserv\Payments\Lib\Version;
use Fiserv\Payments\Gateway\Request\PayPal\Composite\PayPalCompositeBase;
use Fiserv\Payments\Model\Adapter\PayPal\PayPalHttpAdapter;
use Fiserv\Payments\Model\Adapter\PayPal\PayPalHttpResponse;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger as PaymentLogger;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * A client that sends transaction requests to the Fiserv-PayPal API
 */
class HttpClient implements ClientInterface
{
	const STATUS_CODE_KEY = "statusCode";
	const RESPONSE_KEY = "response";
	// TODO: Confirm if this is the file exictly needed or not.

	/**
	 * @var PaymentLogger
	 */
	private $paymentLogger;

	/**
	 * @var PayPalHttpAdapter
	 */
	private $httpAdapter;

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @param PaymentLogger $paymentLogger
	 * @param MultiLevelLogger $logger
	 * @param PayPalHttpAdapter $httpAdapter
	 */
	public function __construct(
		PaymentLogger $paymentLogger,
		PayPalHttpAdapter $httpAdapter,
		MultiLevelLogger $logger
	) {
		$this->paymentLogger = $paymentLogger;
		$this->httpAdapter = $httpAdapter;
		$this->logger = $logger;
	}

	/**
	 * Places request to gateway. Returns result as PayPalHttpResponse object
	 *
	 * @param TransferInterface $transferObject
	 * @return Fiserv\Payments\Lib\CommerceHub\BpResponse
	 * @throws \Magento\Payment\Gateway\Http\ClientException
	 */
	public function placeRequest(TransferInterface $transferObject) 
	{
		$requestBody = $transferObject->getBody();
		$payload = $requestBody[PayPalCompositeBase::REQUEST_KEY];
		$endpoint = $requestBody[PayPalCompositeBase::ENDPOINT_KEY];
	
	    $log = [
			'request' => $requestBody,
		];

        $orderIncrementId = "";
        if (isset($payload['transactionDetails']) && isset($payload['transactionDetails']['merchantOrderId']))
        {
            $orderIncrementId = $payload['transactionDetails']['merchantOrderId'];
        }
        $this->logger->logInfo(1, 'Order ID' . $orderIncrementId);

		$this->logger->logInfo(1, "Sending request to PayPal", "Order ID: $orderIncrementId");
		$this->logger->logDebug(3, "TXN REQUEST INFO", "Order ID: $orderIncrementId");
		$this->logger->logDebug(3, "Payload:\n" . json_encode($payload, JSON_PRETTY_PRINT), "Order ID: $orderIncrementId");

		try {
			$paypalResponse = $this->httpAdapter->sendRequest($payload, $endpoint);
			$responseArray = json_decode($paypalResponse->getBody(), true);

			$this->logger->logInfo(1, "Response received from Commerce Hub", "Order ID: $orderIncrementId");
			$this->logger->logDebug(3, "TXN RESPONSE INFO", "Order ID: $orderIncrementId");
			$this->logger->logDebug(3, "Response Headers:\n" . json_encode($paypalResponse->getHeaders()), "Order ID: $orderIncrementId");
			$this->logger->logDebug(3, "Response Body:\n" . json_encode(json_decode($paypalResponse->getBody()), JSON_PRETTY_PRINT), "Order ID: $orderIncrementId");

			$log['response'] = $paypalResponse->getResponse();
			return [
				self::STATUS_CODE_KEY => $paypalResponse->getStatusCode(),
				self::RESPONSE_KEY => json_decode($paypalResponse->getBody(), true)
			];
		} catch (\Exception $e) {
			$this->logger->logCritical(1, "An error has occurred while sending the payload to Commerce Hub", "Order ID: $orderIncrementId");
			$this->logger->logCritical(2, $e, "Order ID: $orderIncrementId");
			throw new ClientException(
				__('An error occurred in the payment gateway.')
			);
		} finally {
			$this->paymentLogger->debug($log);
		}
	}
}
