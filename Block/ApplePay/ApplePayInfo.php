<?php
namespace Fiserv\Payments\Block\ApplePay;

use Magento\Payment\Block\ConfigurableInfo;
use Magento\Framework\Phrase;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Apple Pay Info Block
 */
class ApplePayInfo extends ConfigurableInfo
{
	/** @var ChConfig */
	private $chConfig;
	private $logger;

	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Magento\Payment\Gateway\ConfigInterface $config,
		ChConfig $chConfig,
		MultiLevelLogger $logger,
		array $data = []
	) {
		parent::__construct($context, $config, $data);
		$this->chConfig = $chConfig;
		$this->logger = $logger;
	}
	/**
	 * Customize label rendering if needed
	 *
	 * @param string $field
	 * @return Phrase
	 */
	protected function getLabel($field)
	{
		return __($field);
	}

	protected function _prepareSpecificInformation($transport = null)
	{
		$transport = parent::_prepareSpecificInformation($transport);
		$paymentInfo = $this->getInfo();

		$ccLast4 = $paymentInfo->getData('cc_last_4');
		$this->setDataToTransfer($transport, "Card Number ", $ccLast4 ? "x" . $ccLast4 : __('N/A'));

		$ccType = $paymentInfo->getData('cc_type');
		$cardsArray = array_flip($this->chConfig->getCcTypesMapper());

		$cardType = isset($cardsArray[$ccType]) ? ucwords(strtolower($cardsArray[$ccType])) : __('Unknown');
		$this->setDataToTransfer($transport, "Card Type", $cardType);

		$transactionId = $paymentInfo->getData('last_trans_id');
		$this->setDataToTransfer($transport, "Transaction ID ", $transactionId);

		return $transport;
	}
}
