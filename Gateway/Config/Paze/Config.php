<?php
namespace Fiserv\Payments\Gateway\Config\Paze;

class Config extends \Magento\Payment\Gateway\Config\Config
{
	const CODE = 'fiserv_paze';
	const KEY_ACTIVE = 'active';
	const KEY_TITLE = 'title';
	const KEY_PAYMENT_ACTION = 'payment_action';
	const KEY_BUTTON_COLOR = 'button_color';
	const KEY_BUTTON_SHAPE = 'button_shape';
	const KEY_DISABLE_MAX_HEIGHT = 'disable_max_height';
	const KEY_BUTTON_LABEL = 'button_label';


	/**
	 * Constructor
	 *
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	 * @param \Magento\Framework\Serialize\Serializer\Json|null $serializer
	 */
	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Framework\Serialize\Serializer\Json $serializer = null
	) {
		parent::__construct($scopeConfig, self::CODE, self::DEFAULT_PATH_PATTERN);
	}

	/**
	 * Is Paze active
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isActive($storeId = null): bool
	{
		return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
	}

	/**
	 * Get Paze title
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getTitle($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_TITLE, $storeId);
	}

	/**
	 * Get Paze payment action
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getPaymentAction($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}

	/**
	 * Get Paze button color
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getButtonColor($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_BUTTON_COLOR, $storeId);
	}

	/**
	 * Get Paze button shape
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getButtonShape($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_BUTTON_SHAPE, $storeId);
	}

	/**
	 * Is disable max height
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isDisableMaxHeight($storeId = null): bool
	{
		return (bool) $this->getValue(self::KEY_DISABLE_MAX_HEIGHT, $storeId);
	}

	/**
	 * Get Paze button label
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getButtonLabel($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_BUTTON_LABEL, $storeId);
	}
}
