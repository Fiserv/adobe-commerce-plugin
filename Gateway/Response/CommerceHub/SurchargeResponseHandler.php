<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Gateway\Response\HandlerInterface;

/** Reads the surcharge value returned by CommerceHub & updates the status History Comment */
class SurchargeResponseHandler implements HandlerInterface
{
	const SURCHARGE_AMOUNT_KEY = 'surcharge_amount';

	/** @var SubjectReader */
	private $subjectReader;

	/** @var MultiLevelLogger */
	private $logger;

	/** @var Config */
	private $config;

	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger,
		Config $config
	) {
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
		$this->config = $config;
	}

	/** @inheritdoc */
	public function handle(array $handlingSubject, array $response)
	{
		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();
		ContextHelper::assertOrderPayment($payment);

		$order = $payment->getOrder();
		$storeId = $order ? (int)$order->getStoreId() : null;
		if (!$this->config->isSurchargeEnabled($storeId)) {return;}

		$chResponse = $this->subjectReader->readChResponse($response)[HttpClient::RESPONSE_KEY];

		$surcharge = $this->extractSurcharge($chResponse);
		if ($surcharge === null || $surcharge <= 0) {
			return;
		}

		$payment->setAdditionalInformation(self::SURCHARGE_AMOUNT_KEY, $surcharge);

		if (!$order) {return;}

		$originalGrandTotal = (float)$order->getGrandTotal();
		$originalBaseGrandTotal = (float)$order->getBaseGrandTotal();
		$newGrandTotal = $originalGrandTotal + $surcharge;
		$newBaseGrandTotal = $originalBaseGrandTotal + $surcharge;

		$order->setGrandTotal($newGrandTotal);
		$order->setBaseGrandTotal($newBaseGrandTotal);
		$payment->setAmountAuthorized($newGrandTotal);
		$payment->setBaseAmountAuthorized($newBaseGrandTotal);

		$createdInvoice = $payment->getCreatedInvoice();
		if ($createdInvoice) {
			$createdInvoice->setGrandTotal((float)$createdInvoice->getGrandTotal() + $surcharge);
			$createdInvoice->setBaseGrandTotal((float)$createdInvoice->getBaseGrandTotal() + $surcharge);
			$order->setTotalInvoiced((float)$order->getTotalInvoiced() + $surcharge);
			$order->setBaseTotalInvoiced((float)$order->getBaseTotalInvoiced() + $surcharge);
		}

		$this->logger->logInfo(
			1,
			sprintf(
				'Surcharge of $%s applied from CommerceHub response to order %s. Grand total updated from $%s to $%s.',
				number_format($surcharge, 2),
				$order->getIncrementId(),
				number_format($originalGrandTotal, 2),
				number_format($newGrandTotal, 2)
			)
		);
	}

	private function extractSurcharge(array $chResponse): ?float
	{
		$value = $chResponse['paymentReceipt']['amountComponents']['surcharge'];
		return $value === null ? null : (float)$value;
	}
}

