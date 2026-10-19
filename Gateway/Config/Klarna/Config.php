<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\Klarna;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	const CODE = 'fiserv_klarna';

	const KEY_ACTIVE = 'payment_active';
	const KEY_TITLE = 'title';
	const KEY_PAYMENT_ACTION = 'payment_action';
	const THEME_KEY = 'button_theme';
	const SHAPE_KEY = 'button_shape';
	const LOGO_ALIGNMENT_KEY = 'button_logo_alignment';

	/**
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	 * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
	 */
	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		?\Magento\Framework\Serialize\Serializer\Json $serializer = null
	)
	{
		parent::__construct($scopeConfig, self::CODE, self::DEFAULT_PATH_PATTERN);
	}

	public function isActive($storeId = null): bool
	{
		return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
	}

	public function getTitle($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_TITLE, $storeId);
	}

	public function getPaymentAction($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}

	/**
	 * Return default, light, dark or outlined; unsupported values use default.
	 */
	public function getTheme($storeId = null): string
	{
		$value = $this->getValue(self::THEME_KEY, $storeId);

		return in_array($value, ['default', 'light', 'dark', 'outlined'], true) ? $value : 'default';
	}

	/**
	 * Return default, rect or pill; unsupported values use default.
	 */
	public function getShape($storeId = null): string
	{
		$value = $this->getValue(self::SHAPE_KEY, $storeId);

		return in_array($value, ['default', 'rect', 'pill'], true) ? $value : 'default';
	}

	/**
	 * Return default, left or center; unsupported values use default.
	 */
	public function getLogoAlignment($storeId = null): string
	{
		$value = $this->getValue(self::LOGO_ALIGNMENT_KEY, $storeId);

		return in_array($value, ['default', 'left', 'center'], true) ? $value : 'default';
	}

	public function getValue($field, $storeId = null)
	{
		$value = parent::getValue($field, $storeId);
		if ($field === self::KEY_PAYMENT_ACTION && $value === 'AUTH') {
			return \Magento\Payment\Model\MethodInterface::ACTION_AUTHORIZE;
		}
		return $value;
	}
}
