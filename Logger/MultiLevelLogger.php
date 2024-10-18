<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Logger;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Logger\MultiLevelLoggerHandler;

/**
 * Class MultiLevelLogger
 */
class MultiLevelLogger extends \Monolog\Logger
{
	/**
	 * @var MultiLevelLoggerHandler
	 */
	private $handler;

	/**
	 * @var Config
	 */
	private $chConfig;
	
	public function __construct(MultiLevelLoggerHandler $handler, Config $config)
	{
		$this->handler = $handler;
		parent::__construct("CommerceHubLogger", [$handler]);
		$this->chConfig = $config;
	}
	
	/**
	 * Array of strings that provide logging level identifiers
	 *
	 * -L1: Represents base level logs. These help track and describe the active process of the plugin.
	 * -L2: Represents error logs. These include both error messages and stack traces.
	 * -L3: Represents any information that may be considered developer logs. These logs provide additional information
	 *      about the objects created by the plugin throughout the transaction.
	 */
	const DEPTH_IDENTIFIER = array(
		1 => "[L1] ",
		2 => "[L2] ",
		3 => "[L3] "
	);
	
	/**
	 * Logging functions
	 */
	// Don't know if docs are wanted for these functions or not :/
	public function logEmergency(int $depth, string|\Stringable $message, array $context = [])
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::EMERGENCY);
		$this->emergency(self::DEPTH_IDENTIFIER[$depth] . $message, $context);
	}
	
	public function logAlert(int $depth, string|\Stringable $message, array $context = [])
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::ALERT);
		$this->logAlert(self::DEPTH_IDENTIFIER[$depth] . $message, $context);
	}

	public function logCritical(int $depth, string|\Stringable $message, array $context = [])
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::CRITICAL);
		$this->critical(self::DEPTH_IDENTIFIER[$depth] . $message, $context);
	}

	public function logError(int $depth, string|\Stringable $message, array $context = [])
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::ERROR);
		$this->error(self::DEPTH_IDENTIFIER[$depth] . $message, $context);
	}

	public function logWarning(int $depth, string|\Stringable $message, array $context = [])
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::WARNING);
		$this->warning(self::DEPTH_IDENTIFIER[$depth] . $message, $context);
	}

	public function logNotice(int $depth, string|\Stringable $message, array $context = [])
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::NOTICE);
		$this->notice(self::DEPTH_IDENTIFIER[$depth] . $message, $context);
	}

	public function logInfo(int $depth, string|\Stringable $message, array $context = [])
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::INFO);
		$this->info(self::DEPTH_IDENTIFIER[$depth] . $message, $context);
	}

	public function logDebug(int $depth, string|\Stringable $message, array $context = [])
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::DEBUG);
		$this->debug(self::DEPTH_IDENTIFIER[$depth] . $message, $context);
	}
}
