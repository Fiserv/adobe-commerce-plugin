<?php
namespace Fiserv\Payments\Model\Adapter\Valuelink;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Magento\Store\Model\StoreManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

/** credentials adapter for the Valuelink (gift card) lane. */
class ValuelinkCredentialsRequest
{
	const CREDENTIALS_ENDPOINT = 'payments-vas/v1/security/credentials';

	// Request keys
	const KEY_DOMAINS = 'domains';
	const KEY_URL = 'url';
	const KEY_MERCHANT_DETAILS = 'merchantDetails';
	const KEY_MERCHANT_ID = 'merchantId';
	const KEY_CHECKOUT_INTERACTIONS = 'checkoutInteractions';
	const KEY_ADDITIONAL_DATA_COMMON = 'additionalDataCommon';
	const KEY_ADDITIONAL_DATA = 'additionalData';
	const KEY_ECOM_URL = 'ecomUrl';

	// Response keys — VAS security/credentials returns all of these
	const KEY_SYMMETRIC_ENCRYPTION_ALGO = 'symmetricEncryptionAlgorithm';
	const KEY_ACCESS_TOKEN = 'accessToken';
	const KEY_SESSION_ID = 'sessionId';
	const KEY_PUBLIC_KEY = 'publicKey';
	const KEY_KEY_ID = 'keyId';

	/** @var Config */
	private $chConfig;

	/** @var ChHttpAdapter */
	private $httpAdapter;

	/** @var StoreManagerInterface */
	private $storeManager;

	/** @var MultiLevelLogger */
	private $logger;

	public function __construct(
		Config $config,
		ChHttpAdapter $httpAdapter,
		StoreManagerInterface $storeManager,
		MultiLevelLogger $logger
	) {
		$this->chConfig = $config;
		$this->httpAdapter  = $httpAdapter;
		$this->storeManager = $storeManager;
		$this->logger = $logger;
	}

	/**
	 * Request VAS security credentials for the gift-card form.
	 */
	public function requestCredentials(): array
	{
		$this->logger->logInfo(1, "Initiating Valuelink (VAS) Credentials Request");
		$data = $this->getCredentialsPayload($this->chConfig->getMerchantId());
		$this->logger->logDebug(3, "Valuelink Credentials Request Payload\n" . json_encode($data, JSON_PRETTY_PRINT));
		$chResponse = $this->httpAdapter->sendRequest($data, self::CREDENTIALS_ENDPOINT);
		return $this->parseResponse($chResponse);
	}

	private function parseResponse($chResponse): array
	{
		$statusCode = $chResponse->getStatusCode();
		$body = $chResponse->getBody();
		$bodyArray = json_decode($body, true);

		if ($statusCode === 201) {
			$this->logger->logInfo(1, "Valuelink credentials request success");
			return [
				self::KEY_SYMMETRIC_ENCRYPTION_ALGO => $bodyArray[self::KEY_SYMMETRIC_ENCRYPTION_ALGO] ?? null,
				self::KEY_ACCESS_TOKEN => $bodyArray[self::KEY_ACCESS_TOKEN] ?? null,
				self::KEY_SESSION_ID => $bodyArray[self::KEY_SESSION_ID] ?? null,
				self::KEY_PUBLIC_KEY => $bodyArray[self::KEY_PUBLIC_KEY] ?? null,
				self::KEY_KEY_ID => $bodyArray[self::KEY_KEY_ID] ?? null,
			];
		}

		$this->logger->logError(1, "Valuelink credentials request failure");
		$this->logger->logError(2, "HTTP error code: {$statusCode}");
		$this->logger->logError(2, "Body: " . json_encode($bodyArray));
		throw new \Exception("CommerceHub Valuelink credentials request HTTP error code: {$statusCode}", 1);
	}

	private function getCredentialsPayload(string $merchantId): array
	{
		$storeUrl = $this->storeManager
			->getStore()
			->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

		return [
			self::KEY_DOMAINS => [[self::KEY_URL => $storeUrl]],
			self::KEY_MERCHANT_DETAILS  => [self::KEY_MERCHANT_ID => $merchantId],
			self::KEY_CHECKOUT_INTERACTIONS => [
				'expiresInMinutes' => $this->chConfig->getCheckoutInteractionsExpiresInMinutes(),
			],
			self::KEY_ADDITIONAL_DATA_COMMON => [
				self::KEY_ADDITIONAL_DATA => [self::KEY_ECOM_URL => $storeUrl],
			],
		];
	}
}

