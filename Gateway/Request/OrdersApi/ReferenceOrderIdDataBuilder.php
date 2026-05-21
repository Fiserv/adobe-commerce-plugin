<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\OrdersApi;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Observer\PayPal\DataAssignObserver;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Adds Merchant Account ID to the request.
 */
class ReferenceOrderIdDataBuilder implements BuilderInterface
{

	const REF_ORDER_KEY = "referenceOrderId";

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 * @param SubjectReader $subjectReader
	 * @param MultiLevelLogger $logger
	 */
	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$orderDO = $paymentDO->getOrder();
        $referenceOrderId = $paymentDO->getPayment()->getAdditionalInformation(DataAssignObserver::ORDER_ID);

		$orderIncrementId = $orderDO->getOrderIncrementId();
		$this->logger->logDebug(3, "Merchant Details Data Builder:\n" . $referenceOrderId, "Order ID: $orderIncrementId");

		return [ self::REF_ORDER_KEY => $referenceOrderId ];
	}
}
