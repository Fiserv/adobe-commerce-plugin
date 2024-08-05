<?php
namespace Fiserv\Payments\Model\Adapter\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Source\CommerceHub\ApiEnvironment;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Fiserv\Payments\Lib\CommerceHub\Model\TokenizationRequest as TokenRequest;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentSession;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails;
use Fiserv\Payments\Gateway\Request\CommerceHub\SessionSourceDataBuilder;

class TokenizationRequest
{
	const TOKENIZATION_ENDPOINT = 'payments-vas/v1/tokens';

	const PAYMENT_TOKENS_KEY = 'paymentTokens';
	const RESPONSE_DESC_KEY = 'tokenResponseDescription';
	const SUCCESS_RESPONSE = 'SUCCESS';
	
	/**
	 * @var Config
	 */
	private $chConfig;

	/**
	 * @var ChHttpAdapter
	 */
	private $httpAdapter;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 */
	public function __construct(
		Config $config,
		ChHttpAdapter $httpAdapter,
	) {
		$this->chConfig = $config;
		$this->httpAdapter = $httpAdapter;
	}

	/**
	 * Performs a credential request to CommerceHub API
	 * to authorize subsequent transactions
	 *
	 * @return array
	 */
	public function tokenizeSession($sessionId)
	{
		$data = $this->getTokenizationPayload($sessionId);
		$response = $this->httpAdapter->sendRequest($data, self::TOKENIZATION_ENDPOINT);

		return $this->parseTokenizationResponse($response);
	}

	private function parseTokenizationResponse($httpResponse) {
		$statusCode = $httpResponse->getStatusCode();
		$response = $httpResponse->getResponse();
		$body = $httpResponse->getBody();		
		$bodyArray = json_decode($body, true);

		if ($statusCode === 200 && $this->isTokenizeSuccessful($bodyArray)) {
			return $bodyArray;
		}
		throw new \Exception('CommerceHub credentials request HTTP error code: ' . $statusCode, 1);
	}

	private function isTokenizeSuccessful($bodyArray) {
		return 
			isset($bodyArray[self::PAYMENT_TOKENS_KEY]) &&
			isset($bodyArray[self::PAYMENT_TOKENS_KEY][0]) &&
			isset($bodyArray[self::PAYMENT_TOKENS_KEY][0][self::RESPONSE_DESC_KEY]) &&
			$bodyArray[self::PAYMENT_TOKENS_KEY][0][self::RESPONSE_DESC_KEY] === self::SUCCESS_RESPONSE;
	}

	/**
	 * Retrieve assoc array of 
	 * CommerceHub TokenizationRequest info
	 *
	 * @param string $sessionId
	 * @return array
	 */
	private function getTokenizationPayload($sessionId) {
		$source = new PaymentSession();
		$source->setSourceType(SessionSourceDataBuilder::PAYMENT_SESSION_SOURCE_TYPE);
		$source->setSessionId($sessionId);

		$merchantDetails = new MerchantDetails();
		$merchantDetails->setMerchantId($this->getMerchantId());
		$merchantDetails->setTerminalId($this->getTerminalId());

		$req = new TokenRequest();
		$req->setSource($source);
		$req->setMerchantDetails($merchantDetails);

		return $req;
	}

	private function getMerchantId() {
		return $this->chConfig->getMerchantId();
	}

	private function getTerminalId() {
		return $this->chConfig->getTerminalId();
	}
}
