<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Validator\CommerceHub\TransactionResponseValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Validates the status of an attempted Sale transaction
 */
class SaleResponseValidator extends TransactionResponseValidator
{	
	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(ResultInterfaceFactory $resultFactory, SubjectReader $subjectReader, MultiLevelLogger $logger)
	{
		parent::__construct($resultFactory, $subjectReader, $logger);
		array_push($this->successStates, self::STATE_CAPTURE);
	}

}
