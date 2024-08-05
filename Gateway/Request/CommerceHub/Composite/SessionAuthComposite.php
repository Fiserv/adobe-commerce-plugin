<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub\Composite;

use Fiserv\Payments\Gateway\Request\CommerceHub\Composite\ChCompositeBase;
use Fiserv\Payments\Lib\CommerceHub\Model\ChargesRequest;
use Fiserv\Payments\Gateway\Request\CommerceHub\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\SessionSourceDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionInteractionDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;

/**
 * Class SessionAuthComposite
 */
class SessionAuthComposite extends ChCompositeBase
{	
	const ENDPOINT = "payments/v1/charges";

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$result = parent::build($buildSubject);

		$req = new ChargesRequest();
		$req->setAmount($result[AmountDataBuilder::AMOUNT_KEY]);
		$req->setSource($result[SessionSourceDataBuilder::SESSION_SOURCE_KEY]);
		$req->setTransactionDetails($result[TransactionDetailsDataBuilder::TXN_DETAILS_KEY]);
		$req->setTransactionInteraction($result[TransactionInteractionDataBuilder::TXN_INTERACTION_KEY]);
		$req->setMerchantDetails($result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY]);

		return [ 
			self::REQUEST_KEY => $req,
			self::ENDPOINT_KEY => self::ENDPOINT
		];
	}
}
