<?php
namespace Fiserv\Payments\Block\Customer\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider;
use Fiserv\Payments\Model\Config\ConfigProvider as FiservConfigProvider;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Customer\Model\Session;

/**
 * Class StoredPaymentsList
 */
class StoredPaymentsList extends Template
{
	const CUSTOMER_ID_KEY = "customer_id";
	const STORE_ID_KEY = "store_id";
	const WEBSITE_ID_KEY = "website_id";
	const FORM_CONFIG_KEY = "formConfig";
	const INVALID_FIELDS_KEY = "invalidFields";
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @var FiservConfigProvider
     */
    private $fiservConfigProvider;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var Session
     */
    private $customerSession;


    public function __construct(
		Context $context,
		Config $config,
		ConfigProvider $configProvider,
		FiservConfigProvider $fiservConfigProvider,
		Json $json,
		Session $customerSession,
		array $data = []
	) {
		parent::__construct($context, $data);
		$this->config = $config;
		$this->configProvider = $configProvider;
		$this->fiservConfigProvider = $fiservConfigProvider;
		$this->json = $json;
		$this->customerSession = $customerSession;
	}

	/**
	 * Can user can create a stored payment account from account page
	 * 	 
	 * @param int|null $storeId
	 * @return boolean
	 */
	public function canCreateStandaloneSpa($storeId = null)
	{
		return $this->config->isActive($storeId) && $this->config->canStandaloneSpa($storeId);
	}

	/**
	 * @return json object
	 */
	public function getConfig()
	{
		$config = array();
		$fconfig = $this->fiservConfigProvider->getConfig()["payment"][FiservConfigProvider::CODE];
		$storeId = $fconfig[FiservConfigProvider::STORE_ID_KEY];

		$chConfig = $this->configProvider->getConfig()["payment"][ConfigProvider::CODE];
		
		$config[ConfigProvider::ENV_KEY] = $chConfig[ConfigProvider::ENV_KEY];
		$config[ConfigProvider::MERCHANT_ID_KEY] = $chConfig[ConfigProvider::MERCHANT_ID_KEY];
		$config[ConfigProvider::API_KEY_KEY] = $chConfig[ConfigProvider::API_KEY_KEY];
		$config[FiservConfigProvider::STORE_URL_KEY] = $fconfig[FiservConfigProvider::STORE_URL_KEY];
		$config[self::CUSTOMER_ID_KEY] = $this->getCustomerId();
		$config[self::STORE_ID_KEY] = $storeId;
		$config[self::WEBSITE_ID_KEY] = $fconfig[FiservConfigProvider::WEBSITE_ID_KEY];
		$config[self::FORM_CONFIG_KEY] = $this->configProvider->buildFormConfig(Config::KEY_SDC_TOKEN, $storeId);
		$config[self::INVALID_FIELDS_KEY] = $this->configProvider->getInvalidFieldMessages(Config::KEY_SDC_TOKEN, $fconfig[FiservConfigProvider::STORE_ID_KEY]); 

		return $this->json->serialize($config);
	}

	private function getCustomerId() {
		return $this->customerSession->getCustomer()->getId();
	}
}
