<?php

namespace Fiserv\Payments\Logger;
use Monolog\Formatter\LineFormatter;
use DateTime;
use DateTimeZone;
use Monolog\LogRecord;
use Monolog\Formatter\NormalizerFormatter;

class CustomDateTimeFormatter extends LineFormatter
{
	protected ?string $logFormat = "[%datetime% %extra.timezone%] %channel%.%level_name%: %message% %context% %extra%\n";

	public function __construct()
	{
		parent::__construct($this->logFormat, NormalizerFormatter::SIMPLE_DATE, true, true, true);
	}

	public function format(array|LogRecord $record): string
	{
		if (is_array($record))
		{
			$datetime = $record['datetime'];
			$timezone = $datetime->getTimezone()->getName();
			$record['extra']['timezone'] = $timezone;
		} else
		{
			$datetime = $record->datetime;
			$timezone = $datetime->getTimezone()->getName();
			$record->extra['timezone'] = $timezone;
		}

		return parent::format($record);
	}
}
