<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub\Composite;

use Fiserv\Payments\Gateway\Request\CommerceHub\Composite\ChCompositeBase;
use Fiserv\Payments\Lib\CommerceHub\Model\ChargesRequest;
use Fiserv\Payments\Gateway\Request\CommerceHub\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\ReferenceTransactionDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionInteractionDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Magento\Framework\ObjectManager\TMapFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class SettleComposite
 */
class SettleComposite extends ChCompositeBase
{	
	const ENDPOINT = "payments/v1/charges";

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
		$this->logger->logInfo(1, "Initiating Capture Transaction");
		
		$result = parent::build($buildSubject);

		$req = new ChargesRequest();
		$req->setAmount($result[AmountDataBuilder::AMOUNT_KEY]);
		$req->setTransactionDetails($result[TransactionDetailsDataBuilder::TXN_DETAILS_KEY]);
		$req->setReferenceTransactionDetails($result[ReferenceTransactionDataBuilder::REF_TXN_KEY]);
		$req->setMerchantDetails($result[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY]);

		return [ 
			self::REQUEST_KEY => $req,
			self::ENDPOINT_KEY => self::ENDPOINT
		];
	}
}
