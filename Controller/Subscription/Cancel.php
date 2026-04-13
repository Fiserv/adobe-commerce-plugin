<?php
declare(strict_types=1);

namespace Fiserv\Payments\Controller\Subscription;

use Fiserv\Payments\Api\Data\SubscriptionOrder\SubscriptionOrderInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\ResourceModel\Subscription\Order\CollectionFactory as SubscriptionCollectionFactory;
use Fiserv\Payments\Model\Subscription\Order as SubscriptionModel;
use Fiserv\Payments\Model\Subscription\OrderFactory as SubscriptionOrderModelFactory;
use Fiserv\Payments\Model\SubscriptionOrder\SubscriptionOrderRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Command\CommandManagerPool;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as SalesOrderCollectionFactory;

class Cancel implements HttpPostActionInterface
{
	private const EXECUTOR_CODE = 'fiserv_commercehub';
	private const COMMAND_CODE = 'void';

	public function __construct(
		private readonly RequestInterface $request,
		private readonly JsonFactory $jsonFactory,
		private readonly SubscriptionOrderRepository $subscriptionRepository,
		private readonly CustomerSession $customerSession,
		private readonly MultiLevelLogger $logger,
		private readonly FormKeyValidator $formKeyValidator,
		private readonly SubscriptionCollectionFactory $subscriptionCollectionFactory,
		private readonly SalesOrderCollectionFactory $salesOrderCollectionFactory,
		private readonly CommandManagerPool $commandManagerPool,
		private readonly SubscriptionOrderModelFactory $subscriptionOrderModelFactory,
		private readonly OrderRepositoryInterface $orderRepository
	) {}

	public function execute(): ResultInterface
	{
		$result = $this->jsonFactory->create();

		try {
			if (!$this->request->isPost()) {
				return $result->setData(['success' => false, 'error' => 'Invalid request method']);
			}

			if (!$this->formKeyValidator->validate($this->request)) {
				return $result->setData(['success' => false, 'error' => 'Invalid form key']);
			}

			$subscriptionId = (int)$this->request->getParam('subscription_id', 0);
			if ($subscriptionId <= 0) {
				return $result->setData(['success' => false, 'error' => 'Missing subscription_id']);
			}

			if (!$this->customerSession->isLoggedIn()) {
				return $result->setData(['success' => false, 'error' => 'Customer not logged in']);
			}

			$currentCustomerId = (int)$this->customerSession->getCustomerId();
			$currentEmail = (string)($this->customerSession->getCustomer()
				? $this->customerSession->getCustomer()->getEmail()
				: '');

			try {
				$subscription = $this->subscriptionRepository->getById($subscriptionId);
			} catch (NoSuchEntityException) {
				return $result->setData(['success' => false, 'error' => 'Subscription not found']);
			}

			// Ownership check
			$ownerId = $subscription->getCustomerId();
			$ownerEmail = (string)$subscription->getCustomerEmail();

			if ($ownerId !== null && (int)$ownerId !== $currentCustomerId) {
				return $result->setData(['success' => false, 'error' => 'Not authorized']);
			}
			if ($ownerId === null && $ownerEmail !== '' && strcasecmp($ownerEmail, $currentEmail) !== 0) {
				return $result->setData(['success' => false, 'error' => 'Not authorized']);
			}

			$clickedIncrementId = (string)$subscription->getOrderIncrementId();
			$rootIncrementId = (string)($subscription->getOriginalOrderIncrement() ?: $clickedIncrementId);
			$rootIncrementId = preg_replace('/-\d+$/', '', $rootIncrementId) ?: $rootIncrementId;

			if ($rootIncrementId === '') {
				return $result->setData(['success' => false, 'error' => 'Missing subscription root order id']);
			}

			$isChildTransaction = (bool)preg_match('/-\d+$/', $clickedIncrementId);

			/**
			 * - If user cancels parent/root: stop future billing (DB only). NO gateway call.
			 * - If user cancels a child (-n): run gateway void (we void that specific child) and mark that row cancelled.
			 */
			if (!$isChildTransaction) {
				// Parent cancel: stop future billing — only update the active head row, never touch processed rows
				$chainCollection = $this->subscriptionCollectionFactory->create()
					->addFieldToFilter('original_order_increment', ['eq' => $rootIncrementId]);

				foreach ($chainCollection as $chainRow) {
					try {
						$chainRowIncrementId = (string)$chainRow->getOrderIncrementId();

						// Mark every row in the chain as is_active = 0 — recurring payment has ended
						$chainRow->setIsActive(0);

						// The head/root row itself represents a real paid transaction.
						// Mark it as 'processed' so it displays correctly after the subscription ends.
						// Also clear any pending card update label — the "Updated" notice must not
						// remain visible once the subscription has been cancelled.
						if ($chainRowIncrementId === $rootIncrementId) {
							if ($chainRow->getStatus() !== SubscriptionModel::STATUS_PROCESSED
								&& $chainRow->getStatus() !== SubscriptionModel::STATUS_CANCELLED
							) {
								$chainRow->setStatus(SubscriptionModel::STATUS_PROCESSED);
							}
							$chainRow->setData('change_payment_card', null);
							$chainRow->save();
							continue;
						}

						// Never touch already-processed or already-cancelled rows' status
						if ($chainRow->getStatus() === SubscriptionModel::STATUS_PROCESSED
							|| $chainRow->getStatus() === SubscriptionModel::STATUS_CANCELLED
						) {
							$chainRow->save();
							continue;
						}

						// Cancel any remaining active scheduling rows (future billing rows)
						$chainRow->setStatus(SubscriptionModel::STATUS_CANCELLED);
						$chainRow->save();
					} catch (\Throwable $exception) {
						$this->logger->logError(2, 'Failed to cancel subscription row', $exception->getMessage());
					}
				}

				// Add cancellation comment to every order in the chain (head + all child renewals)
				try {
					// Collect all increment IDs belonging to this chain
					$chainIncrementIds = [$rootIncrementId];
					$chainSubscriptions = $this->subscriptionCollectionFactory->create()
						->addFieldToFilter('original_order_increment', ['eq' => $rootIncrementId]);
					foreach ($chainSubscriptions as $chainRow) {
						$chainRowIncrementId = (string)$chainRow->getOrderIncrementId();
						if ($chainRowIncrementId !== '' && $chainRowIncrementId !== $rootIncrementId) {
							$chainIncrementIds[] = $chainRowIncrementId;
						}
					}

					// Load all matching sales orders in one collection query
					$chainOrderCollection = $this->salesOrderCollectionFactory->create()
						->addFieldToFilter('increment_id', ['in' => $chainIncrementIds]);

					foreach ($chainOrderCollection as $chainOrder) {
						try {
							$chainOrder->addCommentToStatusHistory(
								'Subscription Canceled. No further recurring payment will occur.'
							);
							$this->orderRepository->save($chainOrder);
						} catch (\Throwable $exception) {
							$this->logger->logError(
								2,
								'Failed to add cancellation comment to order ' . $chainOrder->getIncrementId(),
								$exception->getMessage()
							);
						}
					}
				} catch (\Throwable $exception) {
					$this->logger->logError(2, 'Failed to add cancellation comments to chain orders', $exception->getMessage());
				}

				$this->logger->logInfo(1, "Subscription Recurring Payment finished", "Order ID: {$rootIncrementId}");

				return $result->setData([
					'success' => true,
					'mode' => 'cancel_parent',
					'root' => $rootIncrementId
				]);
			}
			$renewalOrderCollection = $this->salesOrderCollectionFactory->create()
				->addFieldToFilter('increment_id', $clickedIncrementId)
				->setPageSize(1);

			$renewalOrder = $renewalOrderCollection->getFirstItem();
			if (!$renewalOrder || !$renewalOrder->getEntityId()) {
				return $result->setData(['success' => false, 'error' => 'Renewal order not found for ' . $clickedIncrementId]);
			}

			$renewalPayment = $renewalOrder->getPayment();
			if (!$renewalPayment) {
				return $result->setData(['success' => false, 'error' => 'Renewal order has no payment']);
			}

			$this->logger->logInfo(1, 'Voiding child renewal transaction', "Order ID: {$clickedIncrementId}");

			$this->commandManagerPool
				->get(self::EXECUTOR_CODE)
				->executeByCode(self::COMMAND_CODE, $renewalPayment);

			// Mark ONLY this renewal row cancelled
			try {
				$voidedSubscriptionRow = $this->subscriptionOrderModelFactory->create()->load(
					$clickedIncrementId,
					SubscriptionOrderInterface::ORDER_INCREMENT_ID
				);

				if ($voidedSubscriptionRow && $voidedSubscriptionRow->getId()) {
					$voidedSubscriptionRow->setStatus(SubscriptionModel::STATUS_CANCELLED);
					$voidedSubscriptionRow->save();
				}
			} catch (\Throwable $exception) {
				$this->logger->logError(2, 'Failed to mark renewal row cancelled', $exception->getMessage());
			}

			$voidTransactionId = $renewalPayment->getLastTransId() ?: 'N/A';
			$orderTotal = $renewalOrder->getGrandTotal();
			$renewalOrder->addCommentToStatusHistory(
				sprintf(
					'Authorized amount of $%s. Transaction ID: "%s"',
					number_format((float)$orderTotal, 2),
					$voidTransactionId
				)
			);
			$this->orderRepository->save($renewalOrder);

			return $result->setData([
				'success' => true,
				'mode' => 'void_child',
				'order_increment_id' => $clickedIncrementId
			]);
		} catch (\Throwable $exception) {
			$this->logger->logError(1, 'Cancel controller exception', $exception->getMessage());
			return $result->setData(['success' => false, 'error' => $exception->getMessage()]);
		}
	}
}