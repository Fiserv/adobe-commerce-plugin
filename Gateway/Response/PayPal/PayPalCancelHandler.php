<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\PayPal;

use Fiserv\Payments\Gateway\Subject\PayPal\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Gateway\Config\PayPal\Config;

/**
 * Class CancelHandler
 */
class CancelHandler extends PayPalPaymentDetailsHandler implements HandlerInterface
{
	private $subjectReader;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * Constructor
	 *
	 * @param SubjectReader $subjectReader
	 * @param MultiLevelLogger $logger
	 * @param Config $config
	 */
	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger,
		Config $config
	) {
		$this->subjectReader = $subjectReader;
		$this->config = $config;
		parent::__construct($subjectReader, $logger, $config);
	}

	/**
	 * @inheritdoc
	 */
	public function handle(array $handlingSubject, array $response)
	{
		parent::handle($handlingSubject, $response);

 		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();
		
		$payment->setShouldCloseParentTransaction(true);
		$payment->setIsTransactionClosed(true);
	}
}
