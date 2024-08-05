<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Class CancelHandler
 */
class CancelHandler extends PaymentDetailsHandler implements HandlerInterface
{
	private $subjectReader;

	/**
	 * Constructor
	 *
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(
		SubjectReader $subjectReader
	) {
		$this->subjectReader = $subjectReader;
		parent::__construct($subjectReader);
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $handlingSubject, array $response)
	{
		parent::handle($handlingSubject, $response);

 		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();
		
		$payment->setShouldCloseParentTransaction(true);
		$payment->setIsTransactionClosed(true);
	}
}
