<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Block\CommerceHub;

use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Gateway\ConfigInterface;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config;

/**
 * Class Info
 */
class Info extends ConfigurableInfo
{
	private $chConfig;

	public function __construct(
		Context $context,
		ConfigInterface $config,
		Config $chConfig,
		array $data = []
	) {
		parent::__construct($context, $config, $data);
		$this->chConfig = $chConfig;
	}
	
	/**
	 * Returns label
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

		$this->setDataToTransfer(
			$transport,
			"Card Number",
			"x" . $paymentInfo->getData('cc_last_4')
		);
		$cardsArray = array_flip($this->chConfig->getCcTypesMapper());
		$this->setDataToTransfer(
			$transport,
			"Card Type",
			ucwords(strtolower($cardsArray[$paymentInfo->getData('cc_type')]))
		);
		$this->setDataToTransfer(
			$transport,
			"Transaction",
			$paymentInfo->getData('last_trans_id')
		);

		return $transport;
	}
}
