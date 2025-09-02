<?php
namespace Fiserv\Payments\Model\System\Utils\PayPal;

use Fiserv\Payments\Model\Adapter\PayPal\PayPalHttpAdapter;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Fiserv Payments M2 Integration Version
 */
class PayPalTokenUtil
{
	/**
	 * class constants
	 */
	const DELIM = "\$\$TS\$\$=";
    /**
     * @var MultiLevelLogger
     */
	private $logger;
    
    /**
     * @var PayPalHttpAdapter
     */
	private $paypalHttpAdapter;

	public function  __construct(MultiLevelLogger $logger, PayPalHttpAdapter $paypalHttpAdapter)
	{
		$this->logger = $logger;
		$this->paypalHttpAdapter = $paypalHttpAdapter;
	}

	// Necessary because Magento persists SPAs using only the Gateway Token and the Pan as a primary key. 
	// Transarmor returns the same token for cards that have been repeatedly toknenized.
	// This creates non-unique constraint database violations
	public static function formatTokenDataForPersistence(string $tokenData)
	{
		$timestamp = floor(microtime(true) * 1000);
		return $tokenData . self::DELIM . $timestamp;
	}

	public static function getTokenDataForCustomer($customerId): ?string
    {
        try{
            $this->logger->info("Paypal token data retrieved for customer: " . $customerId);
            return $paypalTokenData;
        } catch (\Exception $e) {
            $this->logger->error("Error retrieving Paypal token data for customer: " . $customerId);
            return null;
        }
    }
}