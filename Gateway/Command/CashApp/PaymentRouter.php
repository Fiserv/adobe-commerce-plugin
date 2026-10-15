<?php
namespace Fiserv\Payments\Gateway\Command\CashApp;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Model\CashApp\SavedPayment;
use Magento\Payment\Gateway\CommandInterface;

class PaymentRouter implements CommandInterface
{
	private $reader;
	private $sessionCommand;
	private $tokenCommand;
	private $initialPayment;

	public function __construct(SubjectReader $reader, CommandInterface $sessionCommand,
		CommandInterface $tokenCommand, bool $initialPayment = true)
	{
		$this->reader = $reader;
		$this->sessionCommand = $sessionCommand;
		$this->tokenCommand = $tokenCommand;
		$this->initialPayment = $initialPayment;
	}

	public function execute(array $commandSubject)
	{
		$payment = $this->reader->readPayment($commandSubject)->getPayment();
		$key = $this->initialPayment ? SavedPayment::CHOICE : SavedPayment::USED;
		$saved = in_array($payment->getAdditionalInformation($key), [true, 1, '1', 'true'], true);
		// A declined saved payment must not fall back to a different flow and charge again.
		return ($saved ? $this->tokenCommand : $this->sessionCommand)->execute($commandSubject);
	}
}
