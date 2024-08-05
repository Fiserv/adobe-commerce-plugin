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
/**
 * Class AmountDataBuilder
 */
class AmountDataBuilder implements BuilderInterface
{
	use Formatter;

	const AMOUNT_KEY = "amount";

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * Constructor
	 *
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(SubjectReader $subjectReader)
	{
		$this->subjectReader = $subjectReader;
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

		return [ self::AMOUNT_KEY => $amt ];

	}
}
