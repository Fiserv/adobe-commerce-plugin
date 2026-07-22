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
	const SURCHARGE_INVOICED_KEY = 'surcharge_invoiced';

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

	public function handle(array $handlingSubject, array $response)
	{
		$paymentDO = $this->subjectReader->readPayment($handlingSubject);
		$payment = $paymentDO->getPayment();
		ContextHelper::assertOrderPayment($payment);

		$order = $payment->getOrder();
		if (!$this->config->isSurchargeEnabled((int)$order->getStoreId())) { return; }

		// Surcharge already applied at authorization; CommerceHub is the source of truth.
		if ((float)$payment->getAdditionalInformation(self::SURCHARGE_AMOUNT_KEY) > 0) {
			return;
		}

		$chResponse = $this->subjectReader->readChResponse($response)[HttpClient::RESPONSE_KEY];
		$surcharge = $this->extractSurcharge($chResponse);
		if ($surcharge <= 0) {
			return;
		}

		$payment->setAdditionalInformation(self::SURCHARGE_AMOUNT_KEY, $surcharge);

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
			$payment->setAdditionalInformation(self::SURCHARGE_INVOICED_KEY, $surcharge);
		}

		$baseCurrency = $order->getBaseCurrency();
		$order->addCommentToStatusHistory(
			sprintf(
				'Surcharge applied by gateway: %s. Previous order total: %s. Updated order total: %s',
				$this->formatAmount($baseCurrency, $surcharge),
				$this->formatAmount($baseCurrency, $originalGrandTotal),
				$this->formatAmount($baseCurrency, $newGrandTotal)
			)
		);

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

	private function extractSurcharge(array $chResponse): float
	{
		return (float)($chResponse['paymentReceipt']['amountComponents']['surcharge'] ?? 0);
	}

	private function formatAmount(\Magento\Directory\Model\Currency $currency, float $amount): string
	{
		return $currency->formatTxt($amount);
	}
}
