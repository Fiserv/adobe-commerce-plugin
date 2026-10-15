<?php
namespace Fiserv\Payments\Gateway\Response\CashApp;

use Fiserv\Payments\Gateway\Response\CommerceHub\PaymentDetailsHandler;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Model\CashApp\SavedPayment;

class SavedPaymentDetailsHandler extends PaymentDetailsHandler
{
	private $reader;

	public function __construct(SubjectReader $subjectReader)
	{
		parent::__construct($subjectReader);
		$this->reader = $subjectReader;
	}

	public function handle(array $handlingSubject, array $response)
	{
		parent::handle($handlingSubject, $response);
		$payment = $this->reader->readPayment($handlingSubject)->getPayment();
		$payment->setAdditionalInformation(SavedPayment::USED, true);
		$payment->setAdditionalInformation('cashapp_save_payment_method', false);
	}
}
