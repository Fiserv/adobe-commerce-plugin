<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Validator\CommerceHub\TransactionResponseValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

/**
 * Validates the status of an attempted Cancel transaction
 */
class CancelResponseValidator extends TransactionResponseValidator
{	
	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(ResultInterfaceFactory $resultFactory, SubjectReader $subjectReader)
	{
		parent::__construct($resultFactory, $subjectReader);
		array_push($this->successStates, self::STATE_VOIDED);
	}
}
