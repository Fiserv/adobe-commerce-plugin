<?php

namespace Fiserv\Payments\Model\Plugin\CommerceHub;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as GatewayConfigProvider;
use Magento\Payment\Model\Method\Adapter;

class PaymentMethodAvailable
{
	protected $logger;
	/**
	 * @var Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider
	 */
	private $gatewayConfigProvider;

	public function __construct(
		MultiLevelLogger $logger,
		GatewayConfigProvider $gatewayConfigProvider
	) {
		$this->logger = $logger;
		$this->gatewayConfigProvider = $gatewayConfigProvider;
	}

	public function afterIsAvailable(Adapter $subject, $result)
	{
		if ($subject->getCode() == "fiserv_commercehub")
		{
			$config = $this->gatewayConfigProvider->getConfig()["payment"][GatewayConfigProvider::CODE];
			return $config[GatewayConfigProvider::IS_ACTIVE_KEY] && $config[GatewayConfigProvider::IS_PAYMENT_ACTIVE_KEY];
		}
		return $result;
	}
}
