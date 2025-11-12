<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\OrdersApi;

use Fiserv\Payments\Gateway\Request\OrdersApi\OrderApiTransactionDetailsDataBuilder;

/**
 * Payment Data Builder
 */
class AuthDetailsDataBuilder extends OrderApiTransactionDetailsDataBuilder
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
