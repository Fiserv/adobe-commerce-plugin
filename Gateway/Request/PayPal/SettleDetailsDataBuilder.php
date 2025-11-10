<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Request\PayPal;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Gateway\Config\PayPal\Config;
use Fiserv\Payments\Gateway\Config\Venmo\Config as VenmoConfig;
use Fiserv\Payments\Gateway\Request\PayPal\PayPalTransactionDetailsDataBuilder;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\SplitShipment;
use Magento\Sales\Api\OrderRepositoryInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;


/**
 * Settle Details Data Builder
 */
class SettleDetailsDataBuilder extends PayPalTransactionDetailsDataBuilder
{

	/**
	 * @var OrderRepositoryInterface
	 */
	private $orderRepo;
	private $venmoConfig;

	/**
	 * @param MultiLevelLogger $logger
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger,
		Config $paypalConfig,
		VenmoConfig $venmoConfig,
		OrderRepositoryInterface $orderRepo
	)
	{
		parent::__construct($subjectReader, $paypalConfig, $venmoConfig, $logger);
		$this->orderRepo = $orderRepo;
	}

	public function build(array $buildSubject)
	{
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $orderDO = $paymentDO->getOrder();

        $currentInvoiceTotal = $this->subjectReader->readAmount($buildSubject);
		$grandTotal = round($orderDO->getGrandTotalAmount(), 2, PHP_ROUND_HALF_UP);

		$res = parent::build($buildSubject);
        if (!$this->isPartial($grandTotal, $currentInvoiceTotal))
        {
        	return $res;
        }
		
		$deets = $res[self::TXN_DETAILS_KEY];

		$order = $this->orderRepo->get($orderDO->getId());
		$totalInvoices = 0;
		$invoiceTotalToDate = 0.00;
		$invoices = $order->getInvoiceCollection();
		foreach ($invoices as $_i) {
			$totalInvoices++;
			$invoiceTotalToDate += $_i->getGrandTotal();
		}

		$totalInvoices++;
		$invoiceTotalToDate = round($invoiceTotalToDate, 2, PHP_ROUND_HALF_UP);

		$splitShipment = new SplitShipment();
		$finalInvoice = $grandTotal- $invoiceTotalToDate - $currentInvoiceTotal <= 0.00001;
		$splitShipment->setFinalShipment($finalInvoice);
		$splitShipment->setTotalCount($totalInvoices);
		$deets['splitShipment'] = $splitShipment;
		$res[self::TXN_DETAILS_KEY] = $deets;

		return $res;
	}

	/**
	 * Get Capture Flag
	 * @return bool
	 */
	protected function getCaptureFlag()
	{
		return self::CAPTURE;
	}

    private function isPartial($grandTotal, $currentTotal)
    {
        return $currentTotal < $grandTotal;
    }
}

