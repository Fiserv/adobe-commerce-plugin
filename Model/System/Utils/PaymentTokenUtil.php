<?php
namespace Fiserv\Payments\Model\System\Utils;

/**
 * Fiserv Payments M2 Integration Version
 */
class PaymentTokenUtil
{
	/**
	 * class constants
	 */
	const DELIM = "\$\$TS\$\$=";

	public function  __construct()
	{
	}

	// Necessary because Magento persists SPAs using only the Gateway Token and the Pan as a primary key. 
	// Transarmor returns the same token for cards that have been repeatedly toknenized.
	// This creates non-unique constraint database violations
	public static function formatTokenDataForPersistence(string $tokenData)
	{
		$timestamp = floor(microtime(true) * 1000);
		return $tokenData . self::DELIM . $timestamp;
	}

	public static function getTokenDataFromPersistenceFormat(string $formattedTokenData)
	{
		$pos = strpos($formattedTokenData, self::DELIM);
		if (!$pos)
		{
			return $formattedTokenData;
		}

		return substr($formattedTokenData, 0, $pos);
	}
}