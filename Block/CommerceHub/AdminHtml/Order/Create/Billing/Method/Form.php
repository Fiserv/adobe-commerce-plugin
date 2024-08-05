<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Block\CommerceHub\AdminHtml\Order\Create\Billing\Method;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as GatewayConfig;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider;
use Fiserv\Payments\Model\Config\ConfigProvider as FiservConfigProvider;
use Fiserv\Payments\Model\Source\CommerceHub\CcType;
use Magento\Framework\View\Element\Template\Context;
use Magento\Backend\Model\Session\Quote;
use Magento\Payment\Block\Form\Cc;
use Magento\Payment\Model\Config;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Form
 */
class Form extends Cc
{
	const KEY_CARD = 'payment-card';
	const FORM_CONFIG_KEY = "formConfig";
	const INVALID_FIELDS_KEY = "invalidFields";

	/**
	 * @var Quote
	 */
	protected $sessionQuote;

	/**
	 * @var Config
	 */
	protected $gatewayConfig;

    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @var FiservConfigProvider
     */
    private $fiservConfigProvider;

	/**
	 * @var CcType
	 */
	protected $ccType;

	/**
     * @var Json
     */
    private $json;
	
	/**
	 * @param Context $context
	 * @param Config $paymentConfig
	 * @param Quote $sessionQuote
	 * @param GatewayConfig $gatewayConfig
	 * @param CcType $ccType
	 */
	public function __construct(
		Context $context,
		Config $paymentConfig,
		Quote $sessionQuote,
		GatewayConfig $gatewayConfig,
		ConfigProvider $configProvider,
		FiservConfigProvider $fiservConfigProvider,
		CcType $ccType,
		Json $json,
		array $data = []
	) {
		parent::__construct($context, $paymentConfig, $data);
		$this->sessionQuote = $sessionQuote;
		$this->gatewayConfig = $gatewayConfig;
		$this->configProvider = $configProvider;
		$this->fiservConfigProvider = $fiservConfigProvider;
		$this->ccType = $ccType;
		$this->json = $json;
	}

	/**
	 * Get list of available card types of order billing address country
	 * @return array
	 */
	public function getCcAvailableTypes()
	{
		return $this->getConfiguredCardTypes();
	}

	/**
	 * Check if cvv validation is available
	 * @return boolean
	 */
	public function useCcv()
	{
		return $this->gatewayConfig->isCcvEnabled($this->sessionQuote->getStoreId());
	}

	/**
	 * Get available card types
	 * @return array
	 */
	private function getConfiguredCardTypes()
	{
		$types = $this->ccType->getAllowedTypes();
		$configCardTypes = array_fill_keys(
			$this->gatewayConfig->getAvailableCardTypes($this->sessionQuote->getStoreId()),
			''
		);

		return array_intersect_key($types, $configCardTypes);
	}

	/**
	 * @return json object
	 */
	public function getConfig()
	{
		$config = array();
		$chConfig = $this->configProvider->getConfig()["payment"][ConfigProvider::CODE];
		$fconfig = $this->fiservConfigProvider->getConfig()["payment"][FiservConfigProvider::CODE];

		$config[ConfigProvider::ENV_KEY] = $chConfig[ConfigProvider::ENV_KEY];
		$config[ConfigProvider::MERCHANT_ID_KEY] = $chConfig[ConfigProvider::MERCHANT_ID_KEY];
		$config[ConfigProvider::API_KEY_KEY] = $chConfig[ConfigProvider::API_KEY_KEY];
		$config[FiservConfigProvider::STORE_URL_KEY] = $fconfig[FiservConfigProvider::STORE_URL_KEY];
		$config[self::FORM_CONFIG_KEY] = $this->configProvider->buildFormConfig(GatewayConfig::KEY_SDC_ADMIN, $fconfig[FiservConfigProvider::STORE_ID_KEY]);
		$config[self::INVALID_FIELDS_KEY] = $this->configProvider->getInvalidFieldMessages(GatewayConfig::KEY_SDC_ADMIN, $fconfig[FiservConfigProvider::STORE_ID_KEY]); 
		return $this->json->serialize($config);
	}
}
