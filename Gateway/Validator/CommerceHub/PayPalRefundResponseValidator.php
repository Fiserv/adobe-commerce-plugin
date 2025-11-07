<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Validator\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Validator\CommerceHub\TransactionResponseValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Service\CommerceHub\FailedTransactionManager;

/**
 * Validates the status of an attempted Refund transaction
 */
class PayPalRefundResponseValidator extends TransactionResponseValidator
{

	const PAYMENT_ACTION = "REFUND";

	/**
	 * @var SubjectReader
	 */
	protected $subjectReader;

	/**
	 * @param ResultInterfaceFactory $resultFactory
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(
		ResultInterfaceFactory $resultFactory, 
		SubjectReader $subjectReader, 
		MultiLevelLogger $logger, 
		FailedTransactionManager $failedTxnManager
	) {
		parent::__construct(
			$resultFactory, 
			$subjectReader, 
			$logger, 
			$failedTxnManager
		);
		$this->subjectReader = $subjectReader;
		array_push($this->successStates, self::STATE_REFUNDED);
	}

	protected function getPaymentAction()
	{
		return self::PAYMENT_ACTION;
	}

	public function validate(array $validationSubject): ResultInterface
	{
		$parentResult = parent::validate($validationSubject);
		if (!$parentResult->isValid()) {
			$chRawResponse = $this->subjectReader->readChResponseFromResponse($validationSubject);
			$payment = $this->subjectReader->readPayment($validationSubject)->getPayment();
			$data = [
				'response' => $chRawResponse,
				'paths' => $this->paths,
				'payment_action' => $this->getPaymentAction()
			];
			$payment->setAdditionalInformation('ch_failed_transaction_data', $data);
		}
		return $parentResult; // Ensure to return the result
	}

}
