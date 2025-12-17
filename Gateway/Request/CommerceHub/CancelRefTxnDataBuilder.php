<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\ReferenceTransactionDetails;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\ResourceModel\Subscription\Order\CollectionFactory as SubscriptionCollectionFactory;
use Fiserv\Payments\Api\Data\SubscriptionOrder\SubscriptionOrderInterface;

/**
 * Cancel Reference Transaction Data Builder
 */
class CancelRefTxnDataBuilder implements BuilderInterface
{
	public const REF_TXN_KEY = 'referenceTransaction';

	/**
	 * @param MultiLevelLogger $logger
	 * @param SubjectReader $subjectReader
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function __construct(
		private SubjectReader $subjectReader,
		private MultiLevelLogger $logger,
		private SubscriptionCollectionFactory $subscriptionCollectionFactory
	) {}

	/**
	 * @inheritdoc
	 */
	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$payment = $paymentDO->getPayment();
		$orderDO = $paymentDO->getOrder();
		$currentInc = (string)$orderDO->getOrderIncrementId();
		$rootInc = preg_replace('/-\d+$/', '', $currentInc) ?: $currentInc;

		$refId = '';
		$latestInc = null;

		//subscription order cancel/void
		$coll = $this->subscriptionCollectionFactory->create()
			->addFieldToFilter(SubscriptionOrderInterface::ORIGINAL_ORDER_INCREMENT, ['eq' => $rootInc]);

		$latestRow = null;
		$maxN = -1;

		foreach ($coll as $row) {
			$inc = (string)$row->getOrderIncrementId();

			if ($inc === $rootInc) {
				if ($maxN < 0) {
					$maxN = 0;
					$latestRow = $row;
				}
				continue;
			}

			if (preg_match('/^' . preg_quote($rootInc, '/') . '-(\d+)$/', $inc, $m)) {
				$n = (int)$m[1];
				if ($n > $maxN) {
					$maxN = $n;
					$latestRow = $row;
				}
			}
		}

		if ($latestRow !== null) {
			$latestInc = (string)$latestRow->getOrderIncrementId();
			$refId = (string)$latestRow->getLastGatewayTransactionId();
		}

		//use the payment's auth transaction (for standard non-subscription orders)
		if ($refId === '') {
			$authTransaction = $payment->getAuthorizationTransaction();
			if ($authTransaction !== null) {
				$refId = (string)$authTransaction->getTxnId();
				$this->logger->logDebug(3, 'Cancel: using auth transaction.', 'Order ID: ' . $currentInc);
			}
		}

		$refTxn = new ReferenceTransactionDetails();
		$refTxn->setReferenceTransactionId($refId);
		$this->logger->logDebug(3, "Cancel Reference Transaction Data Builder:\n" . $refTxn->__toString(), 'Order ID: ' . $currentInc . ($latestInc ? " | Latest: $latestInc" : ''));

		return [self::REF_TXN_KEY => $refTxn];
	}
}