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
	const MAJOR = 0;
	const MINOR = 0;
	const TINY = 3;

	public function  __construct()
	{
	}

	public static function getVersionString()
	{
		return self::MAJOR . '.' . self::MINOR . '.' . self::TINY;
	}
}
