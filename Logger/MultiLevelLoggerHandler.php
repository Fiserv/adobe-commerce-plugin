<?php
namespace Fiserv\Payments\Logger;

use Monolog\Logger;

class MultiLevelLoggerHandler extends \Magento\Framework\Logger\Handler\Base
{
	/**
	 * Logging Level
	 *
	 * @var int
	 */
	protected $loggerType = Logger::INFO;

	/**
	 * Log File Name
	 *
	 * @var string
	 */
	protected $fileName = '/var/log/commerce_hub.log';

	/**
	 * Takes in a logging level
	 *
	 * @param int
	 */
	public function setLoggerType(int $loggerType)
	{
		$this->loggerType = $loggerType;
	}
}
