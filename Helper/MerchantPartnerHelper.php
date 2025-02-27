<?php

namespace Fiserv\Payments\Helper;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantPartner;
use Fiserv\Payments\Lib\Version;

class MerchantPartnerHelper 
{
	const PARTNER_ID = "CPA907";
	const PARTNER_TYPE = "PLUGIN";
	const PARTNER_NAME = "Adobe";
	const PARTNER_PRODUCT_NAME = "Adobe Commerce";

	public static function createMerchantPartner(Config $config)
	{
		$merchantPartner = new MerchantPartner();
		$merchantPartner->setId(MerchantPartnerHelper::PARTNER_ID);
		$merchantPartner->setLegacyTppId(MerchantPartnerHelper::PARTNER_ID);
		$merchantPartner->setType(MerchantPartnerHelper::PARTNER_TYPE);
		$merchantPartner->setName(MerchantPartnerHelper::PARTNER_NAME);
		$merchantPartner->setProductName(MerchantPartnerHelper::PARTNER_PRODUCT_NAME);
		$merchantPartner->setVersionNumber(Version::getVersionString());

		$integrator = $config->getMerchantIntegrator();
		if (!is_null($integrator))
		{
			$merchantPartner->setIntegrator($config->getMerchantIntegrator());
		}

		return $merchantPartner;
	}
}
