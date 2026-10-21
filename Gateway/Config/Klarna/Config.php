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
	const KEY_BUTTON_COLOR = 'klarna_button_color';
	const KEY_BUTTON_SHAPE = 'klarna_button_shape';
	const KEY_LOGO_ALIGNMENT = 'klarna_logo_alignment';

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

	/**
	 * Is Klarna active
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isActive($storeId = null): bool
	{
		return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
	}

	/**
	 * Get Klarna title
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getTitle($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_TITLE, $storeId);
	}

	/**
	 * Get Klarna payment action
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getPaymentAction($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}

	/**
	 * Get Klarna ButtonColor
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getKlarnaButtonColor($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_BUTTON_COLOR, $storeId);
	}

	/**
	 * Get Klarna ButtonShape
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getKlarnaButtonShape($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_BUTTON_SHAPE, $storeId);
	}

	/**
	 * Get Klarna LogoAlignment
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getKlarnaLogoAlignment($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_LOGO_ALIGNMENT, $storeId);
	}
}
