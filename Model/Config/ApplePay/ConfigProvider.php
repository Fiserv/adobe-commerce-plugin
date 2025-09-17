<?php
namespace Fiserv\Payments\Model\Config\ApplePay;

use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as CommerceHubConfigProvider;
use Fiserv\Payments\Gateway\Config\ApplePay\Config as ApplePayConfig;
use Magento\Framework\Session\SessionManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Apple Pay ConfigProvider
 */
class ConfigProvider extends CommerceHubConfigProvider
{
	const CODE = 'fiserv_applepay';
	private MultiLevelLogger $logger;

	/**
	 * @var ApplePayConfig
	 */
	protected $config;

	/**
	 * Constructor
	 *
	 * @param ApplePayConfig $config
	 * @param SessionManagerInterface $session
	 */
	public function __construct(
		ApplePayConfig $config,
		SessionManagerInterface $session,
		MultiLevelLogger $logger

) {
		parent::__construct($config, $session);
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * Retrieve Apple Pay checkout configuration
	 *
	 * @return array
	 */
	public function getConfig()
	{
		$storeId = $this->session->getStoreId();

		$this->logger->logInfo(1, $this->config->getEnvironment());
		
		$config = [
			self::IS_ACTIVE_KEY => $this->config->isActive($storeId),
			self::PAYMENT_ACTION_KEY => $this->config->getPaymentAction($storeId),
			self::ENV_KEY => $this->config->getEnvironment($storeId),
			self::MERCHANT_ID_KEY => $this->config->getMerchantId($storeId),
			self::TERMINAL_ID_KEY => $this->config->getTerminalId($storeId),
			self::API_KEY_KEY => $this->config->getApiKey(),
			self::LOGGING_LEVEL_KEY => $this->config->getLoggingLevel($storeId),
			self::THREE_D_SECURE_KEY => $this->config->isThreeDSEnabled($storeId),
			self::PROD_CLIENT_KEY => $this->config->getProdClientUrl(),
			self::CERT_CLIENT_KEY => $this->config->getCertClientUrl(),
			self::QA_CLIENT_KEY => $this->config->getQaClientUrl(),
			self::DEV_CLIENT_KEY => $this->config->getDevClientUrl(),
			self::VAULT_CODE_KEY => self::VAULT_CODE,
			self::CARD_FORM_CONFIG_KEY => $this->buildFormConfig(ApplePayConfig::KEY_SDC_CHECKOUT, $storeId),
			self::INVALID_FIELDS_KEY => $this->getInvalidFieldMessages(ApplePayConfig::KEY_SDC_CHECKOUT, $storeId),
		];
		return ['payment' => [self::CODE => $config]];
	}
}
