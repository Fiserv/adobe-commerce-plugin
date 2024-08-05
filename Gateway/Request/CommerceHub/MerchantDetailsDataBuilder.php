<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Adds Merchant Account ID to the request.
 */
class MerchantDetailsDataBuilder implements BuilderInterface
{

	const MERCHANT_DETAILS_KEY = "merchantDetails";

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var SubjectReader
	 */
	private $subjectReader;

	/**
	 * Constructor
	 *
	 * @param Config $config
	 * @param SubjectReader $subjectReader
	 */
	public function __construct(Config $config, SubjectReader $subjectReader)
	{
		$this->config = $config;
		$this->subjectReader = $subjectReader;
	}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$orderDO = $paymentDO->getOrder();
		$merchantId = $this->config->getMerchantId($orderDO->getStoreId());
		$terminalId = $this->config->getTerminalId($orderDO->getStoreId());
		
		$merchantDetails = new MerchantDetails();
		$merchantDetails->setMerchantId($merchantId);
		$merchantDetails->setTerminalId($terminalId);

		return [ self::MERCHANT_DETAILS_KEY => $merchantDetails ];
	}
}
