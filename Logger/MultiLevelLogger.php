<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Logger;

use DateTimeZone;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Logger\MultiLevelLoggerHandler;
use Magento\Directory\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Fiserv\Payments\Logger\CustomDateTimeFormatter;

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

	public function __construct(MultiLevelLoggerHandler $handler, Config $config, ScopeConfigInterface $scopeConfig, CustomDateTimeFormatter $formatter)
	{
		$this->handler = $handler;
		$this->chConfig = $config;
		$timezone = $scopeConfig->getValue(Data::XML_PATH_DEFAULT_TIMEZONE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$handler->setFormatter($formatter);
		parent::__construct("CommerceHubLogger", [$handler], [], new DateTimeZone($timezone));

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
	public function logEmergency(int $depth, string|\Stringable $message, string $identifier = '')
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::EMERGENCY);
		$this->emergency($this->getLogLine($depth, $identifier, $message));
	}

	public function logAlert(int $depth, string|\Stringable $message, string $identifier = '')
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::ALERT);
		$this->alert($this->getLogLine($depth, $identifier, $message));
	}

	public function logCritical(int $depth, string|\Stringable $message, string $identifier = '')
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::CRITICAL);
		$this->critical($this->getLogLine($depth, $identifier, $message));
	}

	public function logError(int $depth, string|\Stringable $message, string $identifier = '')
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::ERROR);
		$this->error($this->getLogLine($depth, $identifier, $message));
	}

	public function logWarning(int $depth, string|\Stringable $message, string $identifier = '')
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::WARNING);
		$this->warning($this->getLogLine($depth, $identifier, $message));
	}

	public function logNotice(int $depth, string|\Stringable $message, string $identifier = '')
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::NOTICE);
		$this->notice($this->getLogLine($depth, $identifier, $message));
	}

	public function logInfo(int $depth, string|\Stringable $message, string $identifier = '')
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::INFO);
		$this->info($this->getLogLine($depth, $identifier, $message));
	}

	public function logDebug(int $depth, string|\Stringable $message, string $identifier = '')
	{
		if($depth > $this->chConfig->getLoggingLevel())
			return;
		$this->handler->setLoggerType(self::DEBUG);
		$this->debug($this->getLogLine($depth, $identifier, $message));
	}

	private function getLogLine($depth, $identifier, $message)
	{
		return self::DEPTH_IDENTIFIER[$depth] . $this->getIdentifierStub($identifier) . $message;
	}

	private function getIdentifierStub(string $identifier)
	{
		return !empty($identifier) ? "[" . $identifier . "] " : '';
	}
}
