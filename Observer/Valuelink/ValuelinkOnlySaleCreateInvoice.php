<?php

namespace Fiserv\Payments\Observer\Valuelink;

use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Fiserv\Payments\Gateway\Config\Valuelink\Config as ValuelinkConfig;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Payment\Model\MethodInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

class ValuelinkOnlySaleCreateInvoice implements ObserverInterface
{
	private $valuelinkResource;

	private $invoiceSender;

	private $invoiceService;

	private $config;

	private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
		ValuelinkResource $valuelinkResource,
		InvoiceSender $invoiceSender,
		InvoiceService $invoiceService,
		ValuelinkConfig $config,
		MultiLevelLogger $logger
	) {
		$this->valuelinkResource = $valuelinkResource;
		$this->invoiceSender = $invoiceSender;
		$this->invoiceService = $invoiceService;
		$this->config = $config;
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
	{
		$order = $observer->getEvent()->getOrder();
		$incrementId = $order->getIncrementId();
		$valuelinkTxns = $this->valuelinkResource->getByOrderIncrementId($incrementId);

		if ($order->canInvoice() &&
			!empty($valuelinkTxns) &&
			$this->config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE_CAPTURE &&
			$order->getPayment()->getMethodInstance()->getTitle() === "No Payment Information Required"
		) {
			$invoice = $this->invoiceService->prepareInvoice($order);
			$invoice->register();
			$invoice->save();

			$transaction = new Transaction();
			$transactionSave = $transaction->addObject($invoice)->addObject($invoice->getOrder());
			$transactionSave->save();
			$this->invoiceSender->send($invoice);

			$order->setStatus('processing');
			$order->save();
		}
		
        return $this;
    }
}
