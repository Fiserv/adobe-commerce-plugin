<?php
declare(strict_types=1);

namespace Fiserv\Payments\Controller\Adminhtml\Subscription;

use Fiserv\Payments\Api\Data\SubscriptionOrder\SubscriptionOrderInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\ResourceModel\Subscription\Order\CollectionFactory as SubscriptionCollectionFactory;
use Fiserv\Payments\Model\Subscription\Order as SubscriptionModel;
use Fiserv\Payments\Model\Subscription\OrderFactory as SubscriptionOrderModelFactory;
use Fiserv\Payments\Model\SubscriptionOrder\SubscriptionOrderRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Command\CommandManagerPool;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as SalesOrderCollectionFactory;

class Cancel extends Action implements HttpPostActionInterface
{
	private const EXECUTOR_CODE = 'fiserv_commercehub';
	private const COMMAND_CODE = 'void';

	public function __construct(
		Context $context,
		private readonly JsonFactory $jsonFactory,
		private readonly FormKeyValidator $formKeyValidator,
		private readonly SubscriptionOrderRepository $subscriptionRepository,
		private readonly SubscriptionCollectionFactory $subscriptionCollectionFactory,
		private readonly SalesOrderCollectionFactory $salesOrderCollectionFactory,
		private readonly CommandManagerPool $commandManagerPool,
		private readonly SubscriptionOrderModelFactory $subscriptionOrderModelFactory,
		private readonly OrderRepositoryInterface $orderRepository,
		private readonly MultiLevelLogger $logger
	) {
		parent::__construct($context);
	}

	protected function _isAllowed(): bool
	{
		return $this->_authorization->isAllowed('Magento_Backend::admin');
	}

	public function execute(): ResultInterface
	{
		$result = $this->jsonFactory->create();

		try {
			$request = $this->getRequest();

			if (!$request->isPost()) {
				return $result->setData(['success' => false, 'error' => 'Invalid request method']);
			}

			if (!$this->formKeyValidator->validate($request)) {
				return $result->setData(['success' => false, 'error' => 'Invalid form key']);
			}

			$subscriptionId = (int)$request->getParam('subscription_id', 0);
			if ($subscriptionId <= 0) {
				return $result->setData(['success' => false, 'error' => 'Missing subscription_id']);
			}

			try {
				$subscription = $this->subscriptionRepository->getById($subscriptionId);
			} catch (NoSuchEntityException) {
				return $result->setData(['success' => false, 'error' => 'Subscription not found']);
			}

			$clickedIncrementId = (string)$subscription->getOrderIncrementId();
			$rootIncrementId = (string)($subscription->getOriginalOrderIncrement() ?: $clickedIncrementId);
			$rootIncrementId = preg_replace('/-\d+$/', '', $rootIncrementId) ?: $rootIncrementId;

			if ($rootIncrementId === '') {
				return $result->setData(['success' => false, 'error' => 'Missing subscription root order id']);
			}

			$isChildTransaction = (bool)preg_match('/-\d+$/', $clickedIncrementId);

			if (!$isChildTransaction) {
				$chainCollection = $this->subscriptionCollectionFactory->create()
					->addFieldToFilter('original_order_increment', ['eq' => $rootIncrementId]);

				foreach ($chainCollection as $chainRow) {
					try {
						$chainRowIncrementId = (string)$chainRow->getOrderIncrementId();

						// Mark every row in the chain as is_active = 0 — recurring payment has ended
						$chainRow->setIsActive(0);

						// Clear any pending card update label on the head row — the "Updated" notice
						// must not remain visible once the subscription has been cancelled.
						if ($chainRowIncrementId === $rootIncrementId) {
							$chainRow->setData('pending_card_label', null);
						}

						// Only cancel rows that have not already been processed or cancelled
						if ($chainRow->getStatus() === SubscriptionModel::STATUS_PROCESSED
							|| $chainRow->getStatus() === SubscriptionModel::STATUS_CANCELLED
						) {
							$chainRow->save();
							continue;
						}
						$chainRow->setStatus(SubscriptionModel::STATUS_CANCELLED);
						$chainRow->save();
					} catch (\Throwable $exception) {
						$this->logger->logError(2, 'Failed to cancel subscription row', $exception->getMessage());
					}
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

			$this->logger->logInfo(1, 'Admin voiding child renewal transaction', "Order ID: {$clickedIncrementId}");

			$this->commandManagerPool
				->get(self::EXECUTOR_CODE)
				->executeByCode(self::COMMAND_CODE, $renewalPayment);

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
			$this->logger->logError(1, 'Admin Cancel controller exception', $exception->getMessage());
			return $result->setData(['success' => false, 'error' => $exception->getMessage()]);
		}
	}
}