<?php

namespace Fiserv\Payments\Model\Plugin\PayPal;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Model\MethodInterface;

class PayPalFilterMultishipping
{
	protected $logger;

	public function __construct(MultiLevelLogger $logger) 
	{
		$this->logger = $logger;
    }

	public function afterGetMethods(\Magento\Multishipping\Block\Checkout\Billing $subject, array $result)
	{
		$this->logger->logInfo(3, "Filtering Fiserv Payments from multiship");
		return array_filter($result, function (MethodInterface $method) {
			return $method->getCode() !== "fiserv_Paypal";
		});
 	}
}
