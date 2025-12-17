<?php
declare(strict_types=1);
namespace Fiserv\Payments\Controller\Subscription;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Service\SubscriptionPaymentMethodUpdater;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class UpdatePayment implements HttpPostActionInterface
{
	public function __construct(
		private readonly RequestInterface $request,
		private readonly JsonFactory $jsonFactory,
		private readonly CustomerSession $customerSession,
		private readonly FormKeyValidator $formKeyValidator,
		private readonly SubscriptionPaymentMethodUpdater $updater,
		private readonly MultiLevelLogger $logger,
		private readonly OrderRepositoryInterface $orderRepository,
		private readonly SearchCriteriaBuilder $searchCriteriaBuilder
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

			if (!$this->customerSession->isLoggedIn()) {
				return $result->setData(['success' => false, 'error' => 'Customer not logged in']);
			}

			$publicHash = (string)$this->request->getParam('public_hash', '');
			if ($publicHash === '') {
				return $result->setData(['success' => false, 'error' => 'Missing public_hash']);
			}

			$subscriptionId = 0;
			$orderIncrementId = trim((string)$this->request->getParam('order_increment_id', ''));

			if ($orderIncrementId !== '') {
				$orderIncrementId = ltrim($orderIncrementId, "# \t\n\r\0\x0B");
				$subscriptionId = $this->resolveSubscriptionIdFromOrderIncrement($orderIncrementId);
			}

			if ($subscriptionId <= 0) {
				$subscriptionId = (int)$this->request->getParam('subscription_id', 0);
			}

			if ($subscriptionId <= 0) {
				$this->logger->logError(1, 'Missing subscription identifier', 'order_increment_id: ' . ($orderIncrementId ?: 'n/a'));
				return $result->setData(['success' => false, 'error' => 'Missing subscription identifier']);
			}

			$customerId = (int)$this->customerSession->getCustomerId();
			$customerEmail = (string)($this->customerSession->getCustomer()
				? $this->customerSession->getCustomer()->getEmail()
				: '');

			$maskedCard = $this->updater->updateChainPaymentToken($customerId, $customerEmail, $subscriptionId, $publicHash);

			return $result->setData(['success' => true, 'card_label' => $maskedCard ?: '']);
		} catch (\Throwable $e) {
			$this->logger->logError(1, 'UpdatePayment controller exception', $e->getMessage());
			return $result->setData(['success' => false, 'error' => $e->getMessage()]);
		}
	}

	private function resolveSubscriptionIdFromOrderIncrement(string $orderIncrementId): int
	{
		try {
			$searchCriteria = $this->searchCriteriaBuilder
				->addFilter('increment_id', $orderIncrementId, 'eq')
				->setPageSize(1)
				->create();

			$orders = $this->orderRepository->getList($searchCriteria)->getItems();
			if (empty($orders)) {
				$this->logger->logError(1, 'Order not found for increment id', $orderIncrementId);
				return 0;
			}

			$order = reset($orders);
			$subscriptionId = $order->getData('subscription_id');
			if (!empty($subscriptionId) && (int)$subscriptionId > 0) {
				return (int)$subscriptionId;
			}

			$this->logger->logError(1, 'No subscription id found on order', $orderIncrementId);
		} catch (\Throwable $e) {
			$this->logger->logError(1, 'resolveSubscriptionIdFromOrderIncrement failed', $e->getMessage());
		}

		return 0;
	}
}