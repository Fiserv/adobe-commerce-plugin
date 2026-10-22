<?php
namespace Fiserv\Payments\Model\Config\Afterpay;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Fiserv\Payments\Gateway\Config\Afterpay\Config as AfterpayConfig;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as ChConfigProvider;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Store\Model\StoreManagerInterface;

class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_afterpay';
	const STORE_NAME_KEY = 'storeName';
	const THEME_KEY = 'theme';
	const SHAPE_KEY = 'shape';
	const SIZE_KEY = 'size';
	const WIDTH_KEY = 'width';
	const SUPPORTED_CURRENCIES_KEY = 'supportedCurrencies';

	private AfterpayConfig $config;
	private StoreManagerInterface $storeManager;
	private ChConfig $chConfig;
	private CheckoutSession $checkoutSession;

	public function __construct(
		AfterpayConfig $config,
		StoreManagerInterface $storeManager,
		ChConfig $chConfig,
		CheckoutSession $checkoutSession
	) {
		$this->config = $config;
		$this->storeManager = $storeManager;
		$this->chConfig = $chConfig;
		$this->checkoutSession = $checkoutSession;
	}

	public function getConfig()
	{
		$quote = $this->checkoutSession->getQuote();
		$store = $this->storeManager->getStore($quote->getStoreId() ?: null);
		$storeId = $store->getId();
		$environment = $this->chConfig->getApiEnvironment($storeId);

		return ['payment' => [self::CODE => [
			ChConfigProvider::IS_ACTIVE_KEY => $this->config->isActive($storeId)
				&& $this->chConfig->isActive($storeId)
				&& in_array($environment, ['CERT', 'PROD'], true)
				&& $this->config->isCurrencySupported($quote->getQuoteCurrencyCode()),
			ChConfigProvider::PAYMENT_ACTION_KEY => $this->config->getPaymentAction($storeId),
			self::STORE_NAME_KEY => $store->getName(),
			ChConfigProvider::ENV_KEY => $environment,
			self::THEME_KEY => $this->config->getTheme($storeId),
			self::SHAPE_KEY => $this->config->getShape($storeId),
			self::SIZE_KEY => $this->config->getSize($storeId),
			self::WIDTH_KEY => $this->config->getWidth($storeId),
			self::SUPPORTED_CURRENCIES_KEY => AfterpayConfig::SUPPORTED_CURRENCIES,
		]]];
	}
}
