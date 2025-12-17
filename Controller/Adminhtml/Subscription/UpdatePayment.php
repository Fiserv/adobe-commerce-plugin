<?php
declare(strict_types=1);

namespace Fiserv\Payments\Controller\Adminhtml\Subscription;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Service\SubscriptionPaymentMethodUpdater;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Sales\Api\OrderRepositoryInterface;

class UpdatePayment extends Action
{
	/**
	 * IMPORTANT: set this to a real ACL resource that exists in your etc/acl.xml
	 * and is assigned to the admin role that should be allowed to change cards.
	 */
	public const ADMIN_RESOURCE = 'Fiserv_Payments::subscriptions';

	public function __construct(
		Context $context,
		private readonly JsonFactory $jsonFactory,
		private readonly FormKeyValidator $formKeyValidator,
		private readonly SubscriptionPaymentMethodUpdater $updater,
		private readonly MultiLevelLogger $logger,
		private readonly OrderRepositoryInterface $orderRepository,
		private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
		private readonly CustomerRepositoryInterface $customerRepository
	) {
		parent::__construct($context);
	}

	public function execute(): ResultInterface
	{
		/** @var \Magento\Framework\Controller\Result\Json $result */
		$result = $this->jsonFactory->create();

		try {
			$request = $this->getRequest();

			if (!$request->isPost()) {
				return $result->setData(['success' => false, 'error' => 'Invalid request method']);
			}

			if (!$this->formKeyValidator->validate($request)) {
				return $result->setData(['success' => false, 'error' => 'Invalid form key']);
			}

			$publicHash = (string)$request->getParam('public_hash', '');
			if ($publicHash === '') {
				return $result->setData(['success' => false, 'error' => 'Missing public_hash']);
			}

			$subId = (int)$request->getParam('subscription_id', 0);

			// Optional support if you want to pass order_increment_id like storefront
			$orderInc = trim((string)$request->getParam('order_increment_id', ''));
			if ($subId <= 0 && $orderInc !== '') {
				$orderInc = ltrim($orderInc, "# \t\n\r\0\x0B");
				$subId = $this->resolveSubscriptionIdFromOrderIncrement($orderInc);
			}

			if ($subId <= 0) {
				return $result->setData(['success' => false, 'error' => 'Missing subscription identifier']);
			}

			// Prefer explicit customer_id from admin UI/JS
			$customerId = (int)$request->getParam('customer_id', 0);
			$customerEmail = '';

			if ($customerId > 0) {
				try {
					$customer = $this->customerRepository->getById($customerId);
					$customerEmail = (string)$customer->getEmail();
				} catch (\Throwable $e) {
					$this->logger->logError(1, 'Admin UpdatePayment: customer lookup failed', $e->getMessage());
					// keep going; updater may still work if it only needs ID
				}
			}

			if ($customerId <= 0) {
				return $result->setData(['success' => false, 'error' => 'Missing customer_id']);
			}

			// Perform update (same service used by storefront)
			$maskedCard = $this->updater->updateChainPaymentToken($customerId, $customerEmail, $subId, $publicHash);

			return $result->setData(['success' => true, 'card_label' => $maskedCard ?: '']);
		} catch (\Throwable $e) {
			$this->logger->logError(1, 'Admin UpdatePayment controller exception', $e->getMessage());
			return $result->setData(['success' => false, 'error' => $e->getMessage()]);
		}
	}

	private function resolveSubscriptionIdFromOrderIncrement(string $orderIncrementId): int
	{
		try {
			$search = $this->searchCriteriaBuilder
				->addFilter('increment_id', $orderIncrementId, 'eq')
				->setPageSize(1)
				->create();

			$orders = $this->orderRepository->getList($search)->getItems();
			if (empty($orders)) {
				$this->logger->logError(1, 'Admin UpdatePayment: Order not found for increment id', $orderIncrementId);
				return 0;
			}

			$order = reset($orders);

			$subscriptionId = $order->getData('subscription_id');
			if (!empty($subscriptionId) && (int)$subscriptionId > 0) {
				return (int)$subscriptionId;
			}


			$this->logger->logError(1, 'Admin UpdatePayment: No subscription id found on order', $orderIncrementId);
		} catch (\Throwable $e) {
			$this->logger->logError(1, 'Admin UpdatePayment: resolveSubscriptionIdFromOrderIncrement failed', $e->getMessage());
		}

		return 0;
	}
}