<?php
namespace Fiserv\Payments\Lib;

/**
 * Fiserv Payments M2 Integration Version
 */
class Version
{
	/**
	 * class constants
	 */
	const MAJOR = 1;
	const MINOR = 2;
	const TINY = 1;

	public function  __construct()
	{
	}

	public static function getVersionString()
	{
		return self::MAJOR . '.' . self::MINOR . '.' . self::TINY;
	}
}
