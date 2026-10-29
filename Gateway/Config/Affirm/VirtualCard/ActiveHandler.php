<?php
namespace Fiserv\Payments\Gateway\Config\Affirm\VirtualCard;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;


// Makes the Virtual Card method available only when Affirm and Virtual Card are enabled.
class ActiveHandler implements ValueHandlerInterface
{
	private $config;

	public function __construct(Config $config)
	{
		$this->config = $config;
	}

	public function handle(array $subject, $storeId = null)
	{
		return $this->config->isActive($storeId);
	}
}
