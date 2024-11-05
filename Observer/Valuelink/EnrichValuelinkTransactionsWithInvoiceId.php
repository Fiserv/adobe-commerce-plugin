<?php

namespace Fiserv\Payments\Observer\Valuelink;

use Fiserv\Payments\Model\ValuelinkTransaction;
use Fiserv\Payments\Model\Valuelink\Helper\Order\ValuelinkOrderHelper;
use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Magento\Framework\Event\ObserverInterface;

class EnrichValuelinkTransactionsWithInvoiceId implements ObserverInterface
{
	private $valuelinkResource;

	private $orderHelper;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
		ValuelinkResource $valuelinkResource,	
		ValuelinkOrderHelper $orderHelper
	) {
		$this->valuelinkResource = $valuelinkResource;
        $this->orderHelper = $orderHelper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
	{
		$invoice = $observer->getEvent()->getInvoice();
		$order = $invoice->getOrder();
		$incrementId = $order->getIncrementId();

		// Need to filter out canceled transactions as they may be a part of previously failed reorder
		// Thanks Adobe...
		$valuelinkTxns = $this->orderHelper->getValuelinkTransactionsByOrderIncrementId($incrementId);
		$valuelinkTxns = $this->orderHelper->filterCanceledValuelinkTransactions($valuelinkTxns);

		if (!empty($valuelinkTxns))
		{	
			$txnIds = array();
			
			foreach ($valuelinkTxns as $txn)
			{
				if (
					$txn[ValuelinkTransaction::KEY_TRANSACTION_TYPE] === ValuelinkTransaction::CAPTURE_TYPE &&
					$txn[ValuelinkTransaction::KEY_INVOICE_ID] === null)
				{
					array_push($txnIds, $txn[ValuelinkTransaction::KEY_ENTITY_ID]);
					array_push($txnIds, $txn[ValuelinkTransaction::KEY_PARENT_TRANSACTION_ID]);
				}
			}

			$this->valuelinkResource->updateInvoiceId($txnIds, $invoice->getId());
		}

        return $this;
    }
}
