<?php
namespace Fiserv\Payments\Gateway\Config\CashApp;

use Magento\Payment\Gateway\Config\Config as GatewayConfig;

/**
 * Cash App payment configuration.
 */
class Config extends GatewayConfig
{
	public const CODE = 'fiserv_cashapp';
	public const KEY_ACTIVE = 'active';
	public const KEY_PAYMENT_ACTION = 'payment_action';
	public const KEY_BUTTON_THEME = 'cashapp_button_theme';
	public const KEY_BUTTON_SHAPE = 'cashapp_button_shape';
	public const KEY_BUTTON_SIZE = 'cashapp_button_size';
	public const KEY_BUTTON_WIDTH = 'cashapp_button_width';

	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	) {
		parent::__construct($scopeConfig, self::CODE, self::DEFAULT_PATH_PATTERN);
	}

	public function isActive($storeId = null): bool
	{
		return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
	}

	public function getPaymentAction($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}

	public function getButtonTheme($storeId = null): string
	{
		return $this->getAllowedValue(self::KEY_BUTTON_THEME, ['DARK', 'LIGHT'], 'DARK', $storeId);
	}

	public function getButtonShape($storeId = null): string
	{
		return $this->getAllowedValue(self::KEY_BUTTON_SHAPE, ['ROUND', 'SEMIROUND'], 'SEMIROUND', $storeId);
	}

	public function getButtonSize($storeId = null): string
	{
		return $this->getAllowedValue(self::KEY_BUTTON_SIZE, ['MEDIUM', 'SMALL'], 'MEDIUM', $storeId);
	}

	public function getButtonWidth($storeId = null): string
	{
		return $this->getAllowedValue(self::KEY_BUTTON_WIDTH, ['STATIC', 'FULL'], 'STATIC', $storeId);
	}

	private function getAllowedValue(string $key, array $allowed, string $default, $storeId): string
	{
		$value = strtoupper((string) $this->getValue($key, $storeId));

		return in_array($value, $allowed, true) ? $value : $default;
	}
}
