<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\OrdersApi\Fastlane;

use Fiserv\Payments\Gateway\Request\OrdersApi\Fastlane\TransactionDetailsDataBuilder;

/**
 * Payment Data Builder
 */
class AuthDetailsDataBuilder extends TransactionDetailsDataBuilder
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
