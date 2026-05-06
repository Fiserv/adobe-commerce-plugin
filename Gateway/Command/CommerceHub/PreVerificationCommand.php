<?php

namespace Fiserv\Payments\Gateway\Command\CommerceHub;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\CommandInterface;

/**
 * Executes account verification before running the actual payment command.
 */
class PreVerificationCommand implements CommandInterface
{
	/**
	 * @var CommandInterface
	 */
	private $verificationCommand;

	/**
	 * @var CommandInterface
	 */
	private $paymentCommand;

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @param CommandInterface $verificationCommand
	 * @param CommandInterface $paymentCommand
	 * @param MultiLevelLogger $logger
	 */
	public function __construct(
		CommandInterface $verificationCommand,
		CommandInterface $paymentCommand,
		MultiLevelLogger $logger
	) {
		$this->verificationCommand = $verificationCommand;
		$this->paymentCommand = $paymentCommand;
		$this->logger = $logger;
	}

	/**
	 * @inheritdoc
	 */
	public function execute(array $commandSubject)
	{
		$this->logger->logInfo(1, 'Running account verification before payment request');
		$this->verificationCommand->execute($commandSubject);
		$this->paymentCommand->execute($commandSubject);
	}
}

