<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\PayPal;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	/**
	 * Gets config values using field names
	 */
	const KEY_FASTLANE_ACTIVE = 'ch_paypal_fastlane';
	const KEY_MERCHANT_ID = 'merchant_id';
	const KEY_TERMINAL_ID = 'terminal_id';
	const KEY_API_KEY = 'api_key';
	const KEY_API_SECRET = 'api_secret';
	const KEY_ENVIRONMENT = 'api_environment';
	const KEY_PAYMENT_TYPE = 'payment_type';
	const KEY_CURRENCY = 'currency';
	const KEY_PROD_API_SERVICE = 'prod_api_service';
	const KEY_CERT_API_SERVICE = 'cert_api_service';
	const KEY_QA_API_SERVICE = 'qa_api_service';
	const KEY_PROD_CLIENT_URL = 'prod_client_url';
	const KEY_CERT_CLIENT_URL = 'cert_client_url';
	const KEY_QA_CLIENT_URL = 'qa_client_url';
	const KEY_LOGGING_LEVEL = 'logging_level';

	/**
	 * @var \Magento\Framework\Serialize\Serializer\Json
	 */
	private $serializer;

	private $scopeConfig;

	/**
	 * Fiserv PayPal config constructor
	 *
	 * @param ScopeConfigInterface $scopeConfig
	 * @param null|string $methodCode
	 * @param string $pathPattern
	 * @param Json|null $serializer
	 */
	public function __construct(
		ScopeConfigInterface $scopeConfig,
		$methodCode = null,
		$pathPattern = self::DEFAULT_PATH_PATTERN,
		Json $serializer = null
	) {
		parent::__construct($scopeConfig, $methodCode, $pathPattern);
		$this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()->get(Json::class);
		$this->scopeConfig = $scopeConfig;
	}

	/**
	 * Gets Fastlane configuration status.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isFastlaneActive($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_FASTLANE_ACTIVE, $storeId);
	}

	/**
	 * Returns CommerceHub merchant id.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getMerchantId($storeId = null)
	{
		return $this->getValue(self::KEY_MERCHANT_ID, $storeId);
	}

	/**
	 * Returns CommerceHub terminal id.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getTerminalId($storeId = null)
	{
		return $this->getValue(self::KEY_TERMINAL_ID, $storeId);
	}

	/**
	 * Returns CommerceHub API key.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getApiKey($storeId = null)
	{
		return $this->getValue(self::KEY_API_KEY, $storeId);
	}

	/**
	 * Returns CommerceHub API secret.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getApiSecret($storeId = null)
	{
		return $this->getValue(self::KEY_API_SECRET, $storeId);
	}

	/**
	 * Gets value of CommerceHub API environment.
	 *
	 * Possible values: CERT or PROD.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getApiEnvironment($storeId = null)
	{
		return $this->getValue(self::KEY_ENVIRONMENT, $storeId);
	}

	/**
	 * Gets value of configured currency.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getCurrency($storeId = null)
	{
		return $this->getValue(self::KEY_CURRENCY, $storeId);
	}

	/**
	 * Returns URL of CommerceHub API production service.
	 *
	 * @return string
	 */
	public function getProdApiService()
	{
		return $this->getValue(self::KEY_PROD_API_SERVICE);
	}

	/**
	 * Returns URL of CommerceHub API cert service.
	 *
	 * @return string
	 */
	public function getCertApiService()
	{
		return $this->getValue(self::KEY_CERT_API_SERVICE);
	}

	/**
	 * Returns URL of CommerceHub API qa service.
	 *
	 * @return string
	 */
	public function getQaApiService()
	{
		return $this->getValue(self::KEY_QA_API_SERVICE);
	}

	/**
	 * Returns URL of CommerceHub API production SDK.
	 *
	 * @return string
	 */
	public function getProdClientUrl()
	{
		return $this->getValue(self::KEY_PROD_CLIENT_URL);
	}

	/**
	 * Returns URL of CommerceHub API qa SDK.
	 *
	 * @return string
	 */
	public function getQaClientUrl()
	{
		return $this->getValue(self::KEY_QA_CLIENT_URL);
	}

	/**
	 * Returns URL of CommerceHub API cert SDK.
	 *
	 * @return string
	 */
	public function getCertClientUrl()
	{
		return $this->getValue(self::KEY_CERT_CLIENT_URL);
	}

	/**
	 * Returns Logging Level.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getLoggingLevel($storeId = null)
	{
		return $this->getValue(self::KEY_LOGGING_LEVEL, $storeId);
	}
}

