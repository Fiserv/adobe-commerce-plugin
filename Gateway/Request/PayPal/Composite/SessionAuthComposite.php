<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal\Composite;

use Fiserv\Payments\Gateway\Request\PayPal\Composite\PayPalCompositeBase;
use Fiserv\Payments\Lib\CommerceHub\Model\ChargesRequest;
use Fiserv\Payments\Gateway\Request\PayPal\PayPalTransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\PayPal\ReferenceOrderIdDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Fiserv\Payments\Observer\PayPal\DataAssignObserver;
use Magento\Framework\ObjectManager\TMapFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class SessionAuthComposite
 */
class SessionAuthComposite extends PayPalCompositeBase
{
	const ENDPOINT = "checkouts/v1/orders";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @param MultiLevelLogger $logger
	 * @param TMapFactory $tmapFactory
	 * @param array $builders
	 */
	public function __construct(
		MultiLevelLogger $logger, 
		TMapFactory $tmapFactory,
		array $builders = []
	) {
		parent::__construct($tmapFactory, $builders);
		$this->logger = $logger;
	}
	
	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$this->logger->logInfo(1, "Initiating AUTH Transaction");
		
		$result = parent::build($buildSubject);

		$req = new ChargesRequest();
		$req->setTransactionDetails($result[PayPalTransactionDetailsDataBuilder::TXN_DETAILS_KEY]);
		$req->setReferenceTransactionDetails(["referenceOrderId" => $result[ReferenceOrderIdDataBuilder::REF_ORDER_KEY]]);
		$req->setMerchantDetails($result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY]);

		return [ 
			self::REQUEST_KEY => $req,
			self::ENDPOINT_KEY => self::ENDPOINT
		];
	}
}
