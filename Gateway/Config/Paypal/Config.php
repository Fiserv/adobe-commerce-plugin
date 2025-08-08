<?php

namespace Fiserv\Payments\Gateway\Config\Paypal;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	// Constants for PayPal configuration keys
	const KEY_ACTIVE = 'paypal_active';
	const KEY_PAYMENT_ACTION = 'paypal_payment_action';
	const KEY_TITLE = 'paypal_title';
	const KEY_VAULT_ACTIVE = 'paypal_vault_active';

	/**
	 * @var \Magento\Framework\Serialize\Serializer\Json
	 */
	private $serializer;

	/**
	 * PayPal Config constructor
	 *
	 * @param ScopeConfigInterface $scopeConfig
	 * @param null|string $methodCode
	 * @param string $pathPattern
	 * @param Json|null $serializer
	 */
	public function __construct(
		ScopeConfigInterface $scopeConfig,
							 $methodCode = null,
							 $pathPattern = self::DEFAULT_PATH_PATTERN,
		Json $serializer = null
	) {
		parent::__construct($scopeConfig, $methodCode, $pathPattern);
		$this->serializer = $serializer ?: \Magento\Framework\App\ObjectManager::getInstance()->get(Json::class);
	}

	/**
	 * Gets PayPal configuration status.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isPayPalActive($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
	}

	/**
	 * Gets value of PayPal payment action.
	 *
	 * Possible values: Sale or Authorize.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getPayPalPaymentAction($storeId = null)
	{
		return $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
	}

	/**
	 * Retrieve title for PayPal section.
	 *
	 * @param int|null $storeId
	 * @return array
	 */
	public function getPayPalTitle($storeId = null)
	{
		return $this->getValue(self::KEY_TITLE, $storeId);
	}

	/**
	 * Gets vaulting configuration status for PayPal.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isVaultActive($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_VAULT_ACTIVE, $storeId);
	}
}
