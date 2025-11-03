<?php
namespace Fiserv\Payments\Model\Config\ApplePay;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Fiserv\Payments\Gateway\Config\ApplePay\Config as ApplePayConfig;
use Magento\Framework\Session\SessionManagerInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Apple Pay ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
	private MultiLevelLogger $logger;

	/**
	 * @var ApplePayConfig
	 */
	protected $config;

    /**
	 * @var SessionManagerInterface
	 */
	private $session;

	/**
	 * Constructor
	 *
	 * @param ApplePayConfig $config
	 * @param SessionManagerInterface $session
     * @param MultiLevelLogger $logger
	 */
	public function __construct(
		ApplePayConfig $config,
		SessionManagerInterface $session,
		MultiLevelLogger $logger

	) {
		$this->config = $config;
		$this->session = $session;
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

		$config = [
			self::IS_ACTIVE_KEY => $this->config->isActive($storeId),
			self::PAYMENT_ACTION_KEY => $this->config->getPaymentAction($storeId)
		];
		return ['payment' => [ApplePayConfig::CODE => $config]];
	}
}
