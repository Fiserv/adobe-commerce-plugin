<?php

namespace Fiserv\Payments\Logger;
use Monolog\Formatter\LineFormatter;
use DateTime;
use DateTimeZone;

class CustomDateTimeFormatter extends LineFormatter
{
	protected $logFormat = "[%datetime% %extra.timezone%] %channel%.%level_name%: %message% %context% %extra%\n";

	protected $dateFormat = "Y-m-d\TH:i:s.uP";

	public function __construct()
	{
		parent::__construct($this->logFormat, $this->dateFormat, true, true, true);
	}

	public function format(array $record): string
	{
		$datetime = $record['datetime'];
		$timezone = $datetime->getTimezone()->getName();
		$record['extra']['timezone'] = $timezone;

		return parent::format($record);
	}
}
