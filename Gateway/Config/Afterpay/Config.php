<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\Afterpay;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	const CODE = 'fiserv_afterpay';

	const KEY_ACTIVE = 'payment_active';
	const KEY_TITLE = 'title';
	const KEY_PAYMENT_ACTION = 'payment_action';
	const AFTERPAY_BUTTON_THEME_KEY = 'button_theme';
	const AFTERPAY_BUTTON_SHAPE_KEY = 'button_shape';
	const AFTERPAY_BUTTON_SIZE_KEY = 'button_size';
	const AFTERPAY_BUTTON_WIDTH_KEY = 'button_width';

	/** Currencies Afterpay can present; checked against the quote (presentment) currency. */
	const SUPPORTED_CURRENCIES = ['USD', 'AUD', 'CAD', 'NZD', 'GBP'];

	/**
	 * Constructor
	 *
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	 */
	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	)
	{
		parent::__construct($scopeConfig, self::CODE, self::DEFAULT_PATH_PATTERN);
	}

	/**
	 * Is Afterpay active
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isActive($storeId = null): bool
	{
		return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
	}

	/**
	 * Is the given currency one Afterpay can be offered in
	 *
	 * @param string|null $currencyCode
	 * @return bool
	 */
	public function isCurrencySupported($currencyCode): bool
	{
		return in_array(strtoupper((string) $currencyCode), self::SUPPORTED_CURRENCIES, true);
	}

	/**
	 * Get Afterpay title
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getTitle($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_TITLE, $storeId);
	}

	/**
	 * Get Afterpay payment action
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getPaymentAction($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}

	/**
	 * Get a validated Afterpay button theme.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getTheme($storeId = null): string
	{
		$value = $this->getValue(self::AFTERPAY_BUTTON_THEME_KEY, $storeId);

		return in_array($value, ['mint', 'black', 'white'], true) ? $value : 'mint';
	}

	/**
	 * Get a validated Afterpay button shape (semiround is the SDK Semi shape).
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getShape($storeId = null): string
	{
		$value = $this->getValue(self::AFTERPAY_BUTTON_SHAPE_KEY, $storeId);

		return in_array($value, ['round', 'semiround'], true) ? $value : 'round';
	}

	/**
	 * Get a validated Afterpay button size.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getSize($storeId = null): string
	{
		$value = $this->getValue(self::AFTERPAY_BUTTON_SIZE_KEY, $storeId);

		return in_array($value, ['small', 'medium', 'large'], true) ? $value : 'medium';
	}

	/**
	 * Get a validated Afterpay button width.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getWidth($storeId = null): string
	{
		$value = $this->getValue(self::AFTERPAY_BUTTON_WIDTH_KEY, $storeId);

		return in_array($value, ['static', 'full'], true) ? $value : 'static';
	}

	/**
	 * Enforce the sale-only action for Magento's default gateway value handler.
	 *
	 * @param string $field
	 * @param int|null $storeId
	 * @return mixed
	 */
	public function getValue($field, $storeId = null)
	{
		if ($field === self::KEY_PAYMENT_ACTION) {
			return \Magento\Payment\Model\MethodInterface::ACTION_AUTHORIZE_CAPTURE;
		}

		return parent::getValue($field, $storeId);
	}
}
