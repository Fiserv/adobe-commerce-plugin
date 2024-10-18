<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Lib\CommerceHub\Model\Amount;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Magento\Payment\Helper\Formatter;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class AmountDataBuilder
 */
class AmountDataBuilder implements BuilderInterface
{
	use Formatter;

	const AMOUNT_KEY = "amount";

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

		$rawTotal = $this->subjectReader->readAmount($buildSubject);
		
		$amt = new Amount();
		$amt->setTotal(round($rawTotal, 2, PHP_ROUND_HALF_UP));
		$amt->setCurrency($orderDO->getCurrencyCode());

		$this->logger->logInfo(3, "Amount Data Builder:\n" . $amt->__toString());
		return [ self::AMOUNT_KEY => $amt ];
	}
}
