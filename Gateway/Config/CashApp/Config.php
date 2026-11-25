<?php
namespace Fiserv\Payments\Gateway\Config\CashApp;

class Config extends \Magento\Payment\Gateway\Config\Config
{
	const CODE = 'fiserv_cashapp';
	const VAULT_CODE = 'fiserv_cashapp_vault';
	const KEY_ACTIVE = 'payment_active';
	const KEY_BUTTON_THEME = 'cashapp_button_theme';
	const KEY_BUTTON_SHAPE = 'cashapp_button_shape';
	const KEY_BUTTON_SIZE = 'cashapp_button_size';
	const KEY_BUTTON_WIDTH = 'cashapp_button_width';

	const DEFAULT_BUTTON_THEME = 'DARK';
	const DEFAULT_BUTTON_SHAPE = 'ROUND';
	const DEFAULT_BUTTON_SIZE = 'MEDIUM';
	const DEFAULT_BUTTON_WIDTH = 'FULL';

	/**
	 * Constructor
	 *
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	 * @param string|null $methodCode
	 * @param string $pathPattern
	 * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
	 */
	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		$methodCode = self::CODE,
		$pathPattern = self::DEFAULT_PATH_PATTERN,
		?\Magento\Framework\Serialize\Serializer\Json $serializer = null
	) {
		parent::__construct($scopeConfig, $methodCode, $pathPattern);
	}

	/**
	 * Is Cash App active
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isActive($storeId = null): bool
	{
		return !empty($this->getValue(self::KEY_ACTIVE, $storeId));
	}

	/**
	 * Get Cash App button theme
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getButtonTheme($storeId = null): string
	{
		return $this->getValue(self::KEY_BUTTON_THEME, $storeId) ?: self::DEFAULT_BUTTON_THEME;
	}

	/**
	 * Get Cash App button shape
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getButtonShape($storeId = null): string
	{
		return $this->getValue(self::KEY_BUTTON_SHAPE, $storeId) ?: self::DEFAULT_BUTTON_SHAPE;
	}

	/**
	 * Get Cash App button size
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getButtonSize($storeId = null): string
	{
		return $this->getValue(self::KEY_BUTTON_SIZE, $storeId) ?: self::DEFAULT_BUTTON_SIZE;
	}

	/**
	 * Get Cash App button width
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getButtonWidth($storeId = null): string
	{
		return $this->getValue(self::KEY_BUTTON_WIDTH, $storeId) ?: self::DEFAULT_BUTTON_WIDTH;
	}
}
