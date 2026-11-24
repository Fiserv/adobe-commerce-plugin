<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\OrdersApi\Composite;

use Fiserv\Payments\Gateway\Request\OrdersApi\Composite\OrderApiCompositeBase;
use Fiserv\Payments\Lib\CommerceHub\Model\ChargesRequest;
use Fiserv\Payments\Gateway\Request\CommerceHub\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\ReferenceTransactionDataBuilder;
use Fiserv\Payments\Gateway\Request\OrdersApi\OrderApiTransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionInteractionDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Magento\Framework\ObjectManager\TMapFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class SettleComposite
 */
class SettleComposite extends OrderApiCompositeBase
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
	public function __construct(MultiLevelLogger $logger, TMapFactory $tmapFactory, array $builders = [])
	{
		parent::__construct($tmapFactory, $builders);
		$this->logger = $logger;
	}
	
	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		
		$result = parent::build($buildSubject);
		$txnDetails = $result[OrderApiTransactionDetailsDataBuilder::TXN_DETAILS_KEY];
		$orderIncrementId = $txnDetails->getMerchantOrderId() ?? "";
		$this->logger->logInfo(1, "Initiating Capture Transaction", "Order ID: $orderIncrementId");

		$req = new ChargesRequest();
		$req->setAmount($result[AmountDataBuilder::AMOUNT_KEY]);
		$req->setTransactionDetails($txnDetails);
		$req->setReferenceTransactionDetails($result[ReferenceTransactionDataBuilder::REF_TXN_KEY]);
		$req->setMerchantDetails($result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY]);
		return [ 
			self::REQUEST_KEY => $req,
			self::ENDPOINT_KEY => self::ENDPOINT,
			self::ORDER_INCREMENT_ID_KEY => $orderIncrementId
		];
	}
}
