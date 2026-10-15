<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Config\AffirmVirtualCard;

use Fiserv\Payments\Gateway\Config\Affirm\Config as AffirmConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
	const CODE = 'fiserv_affirm_virutal_card';

	const KEY_ACTIVE = 'payment_active';
	const KEY_TITLE = 'title';
	const KEY_PAYMENT_ACTION = 'payment_action';

	/**
	 * @var AffirmConfig
	 */
	private $affirmConfig;

	/**
	 * @param ScopeConfigInterface $scopeConfig
	 * @param AffirmConfig $affirmConfig
	 * @param string|null $methodCode
	 * @param string $pathPattern
	 * @param Json|null $serializer
	 */
	public function __construct(
		ScopeConfigInterface $scopeConfig,
		AffirmConfig $affirmConfig,
		$methodCode = self::CODE,
		$pathPattern = self::DEFAULT_PATH_PATTERN,
		?Json $serializer = null
	)
	{
		parent::__construct($scopeConfig, $methodCode, $pathPattern);
		$this->affirmConfig = $affirmConfig;
	}

	/**
	 * Keep adapter availability and direct config reads consistent.
	 *
	 * @param string $field
	 * @param int|null $storeId
	 * @return mixed
	 */
	public function getValue($field, $storeId = null)
	{
		if ($field === 'active')
		{
			return $this->isActive($storeId);
		}

		if ($field === self::KEY_PAYMENT_ACTION)
		{
			return $this->getPaymentAction($storeId);
		}

		return parent::getValue($field, $storeId);
	}

	/**
	 * Honor the adapter flag and both store-scoped payment toggles.
	 * Read the raw adapter flag through the parent to avoid recursion.
	 *
	 * @param int|null $storeId
	 * @return bool
	 */
	public function isActive($storeId = null): bool
	{
		return (bool) parent::getValue('active', $storeId)
			&& (bool) $this->getValue(self::KEY_ACTIVE, $storeId)
			&& $this->affirmConfig->isActive($storeId)
			&& in_array($this->getPaymentAction($storeId), ['AUTH', \Magento\Payment\Model\MethodInterface::ACTION_AUTHORIZE], true);
	}

	/**
	 * @param int|null $storeId
	 * @return string
	 */
	public function getTitle($storeId = null): string
	{
		return (string) $this->getValue(self::KEY_TITLE, $storeId);
	}

	/**
	 * Reuse Affirm's intent, matching FiservAffirmValueHandler.
	 *
	 * @param int|null $storeId
	 * @return string
	 */
	public function getPaymentAction($storeId = null): string
	{
		$action = (string) $this->affirmConfig->getPaymentAction($storeId);
		return $action === 'AUTH' ? \Magento\Payment\Model\MethodInterface::ACTION_AUTHORIZE : $action;
	}
}
