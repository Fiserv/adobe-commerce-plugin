<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal\Composite;

use Fiserv\Payments\Gateway\Request\PayPal\Composite\PayPalCompositeBase;
use Fiserv\Payments\Lib\PayPal\Model\RefundRequest;
use Fiserv\Payments\Gateway\Request\PayPal\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\PayPal\ReferenceTransactionDataBuilder;
use Fiserv\Payments\Gateway\Request\PayPal\PayPalTransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\PayPal\PayPalTransactionInteractionDataBuilder;
use Fiserv\Payments\Gateway\Request\PayPal\MerchantDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\PayPal\CustomerDataBuilder;
use Magento\Framework\ObjectManager\TMapFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class RefundComposite
 */
class RefundComposite extends PayPalCompositeBase
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
		$orderIncrementId = $result[PayPalTransactionDetailsDataBuilder::TXN_DETAILS_KEY]['merchant_order_id'] ?? null;
		if ($orderIncrementId !== null) {
			$this->logger->logInfo(1, "Initiating Refund Transaction", "Order ID:" . $orderIncrementId);
		} else {
			$this->logger->logInfo(1, "Initiating Refund Transaction");
		}

		$req = new RefundRequest();
		$req->setTransactionDetails($result[PayPalTransactionDetailsDataBuilder::TXN_DETAILS_KEY]);
		$req->setReferenceTransactionDetails($result[ReferenceTransactionDataBuilder::REF_TXN_KEY]);
		$req->setMerchantDetails($result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY]);

		return [ 
			self::REQUEST_KEY => $req,
			self::ENDPOINT_KEY => self::ENDPOINT
		];
	}
}
