<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal;

use Fiserv\Payments\Gateway\Request\PayPal\PayPalTransactionDetailsDataBuilder;

/**
 * Payment Data Builder
 */
class AuthDetailsDataBuilder extends PayPalTransactionDetailsDataBuilder
{
	/**
	 * Get Capture Flag
	 * @return bool
	 */
	protected function getCaptureFlag()
	{
		return self::AUTHORIZE;
	}
}
