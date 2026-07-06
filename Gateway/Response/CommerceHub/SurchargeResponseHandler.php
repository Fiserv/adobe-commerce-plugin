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
	const SURCHARGE_CAPTURED_KEY = 'surcharge_capture_amount';

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
		if (!$order) {return;}
		if (!$this->config->isSurchargeEnabled((int)$order->getStoreId())) {return;}

		$alreadyApplied = $payment->getAdditionalInformation(self::SURCHARGE_AMOUNT_KEY);

		$chResponse = $this->subjectReader->readChResponse($response)[HttpClient::RESPONSE_KEY];

		if ($alreadyApplied !== null && (float)$alreadyApplied > 0) {
			// Surcharge was already applied during auth — do not modify order totals again.
			// Store the capture-specific surcharge so SurchargeAuthorizationComment can
			// correct the "Captured amount of $X" comment to show the actual gateway total.
			$captureSurcharge = $this->extractSurcharge($chResponse);
			if ($captureSurcharge !== null && $captureSurcharge > 0) {
				$payment->setAdditionalInformation(self::SURCHARGE_CAPTURED_KEY, $captureSurcharge);
			}
			return;
		}

		$surcharge = $this->extractSurcharge($chResponse);

		if ($surcharge === null || $surcharge <= 0) {
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
		$surchargeFormatted = $baseCurrency ? $baseCurrency->formatTxt($surcharge) : (string)$surcharge;
		$originalGrandTotalFormatted = $baseCurrency ? $baseCurrency->formatTxt($originalGrandTotal) : (string)$originalGrandTotal;
		$newGrandTotalFormatted = $baseCurrency ? $baseCurrency->formatTxt($newGrandTotal) : (string)$newGrandTotal;
		$order->addCommentToStatusHistory(
			sprintf(
				'Surcharge applied by gateway: %s. Previous order total: %s. Updated order total: %s',
				$surchargeFormatted,
				$originalGrandTotalFormatted,
				$newGrandTotalFormatted
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

	private function extractSurcharge(array $chResponse): ?float
	{
		$value = $chResponse['paymentReceipt']['amountComponents']['surcharge'] ?? null;
		return $value === null ? null : (float)$value;
	}
}

