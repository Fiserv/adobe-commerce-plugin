<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Config\Ach;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Fiserv\Payments\Gateway\Config\Ach\Config as AchConfig;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider as ChConfigProvider;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ConfigProvider
 * 
 * ACH ConfigProvider - minimal config, reuses CommerceHub credentials
 */
class ConfigProvider implements ConfigProviderInterface
{
	const CODE = 'fiserv_ach';

	/**
	 * @var AchConfig
	 */
	private $config;

	/**
	 * @var ChConfig
	 */
	private $chConfig;

	/**
	 * @var SessionManagerInterface
	 */
	private $session;

	/**
	 * @var StoreManagerInterface
	 */
	private $storeManager;

	public function __construct(
		AchConfig $config,
		ChConfig $chConfig,
		SessionManagerInterface $session,
		StoreManagerInterface $storeManager
	) {
		$this->config = $config;
		$this->chConfig = $chConfig;
		$this->session = $session;
		$this->storeManager = $storeManager;
	}

	/**
	 * @return array
	 */
	public function getConfig()
	{
		$storeId = $this->session->getStoreId();

		$config = [
			ChConfigProvider::IS_ACTIVE_KEY => $this->config->isActive($storeId),
			ChConfigProvider::PAYMENT_ACTION_KEY => $this->config->getPaymentAction($storeId),
			ChConfigProvider::CARD_FORM_CONFIG_KEY => $this->buildAchFormConfig($storeId),
		];

		return ['payment' => [self::CODE => $config]];
	}

	/**
	 * Build ACH form config for SDK - uses BANK_ACCOUNT payment type
	 *
	 * @param int $storeId
	 * @return array
	 */
	private function buildAchFormConfig($storeId)
	{
		$fieldsConfig = [];

		// Get field configurations from ACH Config
		$routingNumberConfig = $this->config->routingNumberFormConfig($storeId);
		$accountNumberConfig = $this->config->accountNumberFormConfig($storeId);
		$idTypeConfig = $this->config->idTypeFormConfig($storeId);
		$idValueConfig = $this->config->idValueFormConfig($storeId);
		$driverLicenseStateConfig = $this->config->driverLicenseStateFormConfig($storeId);
		$checkTypeConfig = $this->config->checkTypeFormConfig($storeId);
		$accountTypeConfig = $this->config->accountTypeFormConfig($storeId);
		$businessNameConfig = $this->config->businessNameFormConfig($storeId);

		$fieldsConfig["fields"] = [
			"accountNumber" => [
				"parentElementId" => $this->resolveParentElement($accountNumberConfig, 'fiserv_ach-account-number'),
				"placeholder" => $accountNumberConfig[AchConfig::KEY_SDC_PLACEHOLDER],
				"masking" => [
					"character" => $accountNumberConfig[AchConfig::KEY_SDC_MASK_CHAR],
					"mode" => $accountNumberConfig[AchConfig::KEY_SDC_MASK_MODE]
				]
			],
			"routingNumber" => [
				"parentElementId" => $this->resolveParentElement($routingNumberConfig, 'fiserv_ach-routing-number'),
				"placeholder" => $routingNumberConfig[AchConfig::KEY_SDC_PLACEHOLDER],
				"masking" => [
					"character" => $routingNumberConfig[AchConfig::KEY_SDC_MASK_CHAR],
					"mode" => $routingNumberConfig[AchConfig::KEY_SDC_MASK_MODE]
				]
			],
			"idType" => [
				"parentElementId" => $this->resolveParentElement($idTypeConfig, 'fiserv_ach-id-type'),
				"placeholder" => $idTypeConfig[AchConfig::KEY_SDC_PLACEHOLDER]
			],
			"idValue" => [
				"parentElementId" => $this->resolveParentElement($idValueConfig, 'fiserv_ach-id-value'),
				"placeholder" => $idValueConfig[AchConfig::KEY_SDC_PLACEHOLDER]
			],
			"driverLicenseState" => [
				"parentElementId" => $this->resolveParentElement($driverLicenseStateConfig, 'fiserv_ach-driver-license-state'),
				"placeholder" => $driverLicenseStateConfig[AchConfig::KEY_SDC_PLACEHOLDER]
			],
			"checkType" => [
				"parentElementId" => $this->resolveParentElement($checkTypeConfig, 'fiserv_ach-check-type'),
				"placeholder" => $checkTypeConfig[AchConfig::KEY_SDC_PLACEHOLDER]
			],
			"accountType" => [
				"parentElementId" => $this->resolveParentElement($accountTypeConfig, 'fiserv_ach-account-type'),
				"placeholder" => $accountTypeConfig[AchConfig::KEY_SDC_PLACEHOLDER]
			],
			"businessName" => [
				"parentElementId" => $this->resolveParentElement($businessNameConfig, 'fiserv_ach-business-name'),
				"placeholder" => $businessNameConfig[AchConfig::KEY_SDC_PLACEHOLDER]
			]
		];

		$fieldsConfig["paymentMethod"] = "BANK_ACCOUNT";
		$fieldsConfig["css"] = json_decode($this->chConfig->cssFormConfig(ChConfig::KEY_SDC_CHECKOUT, $storeId) ?? "{}");

		$fontConfig = $this->chConfig->fontFormConfig(ChConfig::KEY_SDC_CHECKOUT, $storeId) ?? [];
		if ($this->hasRequiredFontConfig($fontConfig)) {
			$fieldsConfig["font"] = $fontConfig;
		}

		return $fieldsConfig;
	}

	private function hasRequiredFontConfig($fontConfig)
	{
		return
			!empty(trim((string)($fontConfig[ChConfig::KEY_FONT_DATA] ?? ''))) &&
			!empty(trim((string)($fontConfig[ChConfig::KEY_FONT_FAMILY] ?? ''))) &&
			!empty(trim((string)($fontConfig[ChConfig::KEY_FONT_FORMAT] ?? '')));
	}

	private function resolveParentElement(array $fieldConfig, string $fallback): string
	{
		$parentElementId = trim((string)($fieldConfig[AchConfig::KEY_SDC_PARENT_ELEMENT] ?? ''));
		return $parentElementId !== '' ? $parentElementId : $fallback;
	}

}
