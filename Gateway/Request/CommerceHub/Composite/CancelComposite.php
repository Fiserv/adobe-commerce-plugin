<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub\Composite;

use Fiserv\Payments\Gateway\Request\CommerceHub\Composite\ChCompositeBase;
use Fiserv\Payments\Lib\CommerceHub\Model\CancelRequest;
use Fiserv\Payments\Gateway\Request\CommerceHub\ReferenceTransactionDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionInteractionDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;

/**
 * Class CancelComposite
 */
class CancelComposite extends ChCompositeBase
{	
	const ENDPOINT = "payments/v1/cancels";

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$result = parent::build($buildSubject);

		$req = new CancelRequest();
		$req->setReferenceTransactionDetails($result[ReferenceTransactionDataBuilder::REF_TXN_KEY]);
		$req->setMerchantDetails($result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY]);

		return [ 
			self::REQUEST_KEY => $req,
			self::ENDPOINT_KEY => self::ENDPOINT
		];
	}
}
