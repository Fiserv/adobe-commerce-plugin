<?php
namespace Fiserv\Payments\Gateway\Config\Affirm\VirtualCard;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;

/**
 * Affirm Virtual Card uses the same Affirm Charge Type setting.
 */
class PaymentActionHandler implements ValueHandlerInterface
{
	/**
	 * @var Config
	 */
	private $config;

	public function __construct(Config $config)
	{
		$this->config = $config;
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $subject, $storeId = null)
	{
		return $this->config->getPaymentAction($storeId);
	}
}
