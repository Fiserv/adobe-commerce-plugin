<?php

namespace Fiserv\Payments\Observer\Valuelink;

use Fiserv\Payments\Model\Adapter\Valuelink\ValuelinkCancelRequest;
use Fiserv\Payments\Model\Service\Valuelink\ValuelinkTransactionManager;
use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;
use Fiserv\Payments\Model\Valuelink\Helper\Invoice\ValuelinkInvoiceHelper;
use Fiserv\Payments\Model\ValuelinkTransaction;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Event\ObserverInterface;

class CaptureValuelinkTransactions implements ObserverInterface
{
	private $orderHelper;

	private $invoiceHelper;
	
	private $valuelinkTransactionManager;
	

    /**
     * @param MultiLevelLogger $logger
     */
    public function __construct(
		ValuelinkOrderHelper $orderHelper,
		ValuelinkInvoiceHelper $invoiceHelper,
		ValuelinkTransactionManager $valuelinkTransactionManager,

	) {
		$this->orderHelper = $orderHelper;
		$this->invoiceHelper = $invoiceHelper;
		$this->valuelinkTransactionManager = $valuelinkTransactionManager;

    }

    public function execute(\Magento\Framework\Event\Observer $observer)
	{
		$invoice = $observer->getEvent()->getInvoice();
		$order = $invoice->getOrder();

		$isFullInvoice = $this->invoiceHelper->isFullInvoice($order, $invoice);
		$remainingAuth = $this->orderHelper->getRemainingAuthAmount($order);
		$valuelinkInvoiceAmount = $this->invoiceHelper->getValuelinkInvoiceAmount($order, $invoice, $remainingAuth);

		// Capture command will capture uncaptured Valuelink authorizations
		// Orders being captured can contain uncaptured AND captured Valuelink authorizations.
		$balancedAuths = $this->orderHelper->getBalancedAuths($order);

		if (count($balancedAuths))
		{
			$authOrder = $this->invoiceHelper->getAuthCaptureSequence($valuelinkInvoiceAmount, $balancedAuths);
			
			$totalCaptured = 0;
			foreach ($authOrder as $auth)
			{
				$this->valuelinkTransactionManager->captureValuelinkTransaction($invoice, $auth["auth"], $auth["captureAmount"], $auth["captures"], $auth["final"]);
			}
		}

        return $this;
	}
}
