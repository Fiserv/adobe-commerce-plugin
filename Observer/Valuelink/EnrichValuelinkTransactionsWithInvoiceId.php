<?php

namespace Fiserv\Payments\Observer\Valuelink;

use Fiserv\Payments\Model\ValuelinkTransaction;
use Fiserv\Payments\Model\ResourceModel\ValuelinkTransaction as ValuelinkResource;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class EnrichValuelinkTransactionsWithInvoiceId implements ObserverInterface
{
	private $valuelinkResource;

	private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
		ValuelinkResource $valuelinkResource,	
		LoggerInterface $logger
	) {
		$this->valuelinkResource = $valuelinkResource;
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
	{
		$invoice = $observer->getEvent()->getInvoice();
		$order = $invoice->getOrder();
		$incrementId = $order->getIncrementId();

		$valuelinkTxns = $this->valuelinkResource->getByOrderIncrementId($incrementId);

		if (!empty($valuelinkTxns))
		{	
			$txnIds = array();
			
			foreach ($valuelinkTxns as $txn)
			{
				if (
					$txn[ValuelinkTransaction::KEY_PARENT_TRANSACTION_ID] !== null &&
					$txn[ValuelinkTransaction::KEY_TRANSACTION_STATE] === ValuelinkTransaction::CAPTURED_STATE &&
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
