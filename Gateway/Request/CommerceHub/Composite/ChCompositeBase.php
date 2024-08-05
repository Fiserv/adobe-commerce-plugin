<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub\Composite;

use Magento\Payment\Gateway\Request\BuilderComposite;

/**
 * Class SessionAuthComposite
 */
abstract class ChCompositeBase extends BuilderComposite
{	
	const REQUEST_KEY = "request";
	const ENDPOINT_KEY = "endpoint";
}
