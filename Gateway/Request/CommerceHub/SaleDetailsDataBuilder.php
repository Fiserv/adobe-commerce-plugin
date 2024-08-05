<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionDetailsDataBuilder;

/**
 * Payment Data Builder
 */
class SaleDetailsDataBuilder extends TransactionDetailsDataBuilder
{
	/**
	 * Get Capture Flag
	 * @return bool
	 */
	protected function getCaptureFlag()
	{
		return self::CAPTURE;
	}
}
