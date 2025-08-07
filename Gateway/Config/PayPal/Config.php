<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\PayPal;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	/**
	 * Gets config values using field names
	 */
	const KEY_FASTLANE_ACTIVE = 'ch_paypal_fastlane';

	/**
	 * @var \Magento\Framework\Serialize\Serializer\Json
	 */
	private $serializer;

	private $scopeConfig;

	/**
	 * Fiserv PayPal config constructor
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
		$this->scopeConfig = $scopeConfig;
	}

	/**
	 * Gets Fastlane configuration status.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isFastlaneActive($storeId = null)
	{
		return (bool) $this->getValue(self::KEY_FASTLANE_ACTIVE, $storeId);
	}
}

