<?php
namespace Fiserv\Payments\Controller\ApplePay;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilder;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Magento\Sales\Model\Order\Payment\Transaction;

class Validate extends Action implements CsrfAwareActionInterface
{
	protected $checkoutSession;
	protected $cartManagement;
	protected $cartRepository;
	protected $quoteManagement;
	protected $resultJsonFactory;
	protected $logger;
	protected $orderRepository;
	protected $transactionBuilder;
	protected $transactionRepository;
	protected $commandPool;
	protected $paymentDataObjectFactory;
	protected $commerceHubConfig;

	public function __construct(
		Context $context,
		CheckoutSession $checkoutSession,
		CartManagementInterface $cartManagement,
		CartManagementInterface $quoteManagement,
		CartRepositoryInterface $cartRepository,
		JsonFactory $resultJsonFactory,
		MultiLevelLogger $logger,
		OrderRepositoryInterface $orderRepository,
		TransactionBuilder $transactionBuilder,
		TransactionRepositoryInterface $transactionRepository,
		CommandPoolInterface $commandPool,
		PaymentDataObjectFactory $paymentDataObjectFactory,
		CommerceHubConfig $commerceHubConfig
	) {
		parent::__construct($context);
		$this->checkoutSession = $checkoutSession;
		$this->cartManagement = $cartManagement;
		$this->quoteManagement = $quoteManagement;
		$this->cartRepository = $cartRepository;
		$this->resultJsonFactory = $resultJsonFactory;
		$this->logger = $logger;
		$this->orderRepository = $orderRepository;
		$this->transactionBuilder = $transactionBuilder;
		$this->transactionRepository = $transactionRepository;
		$this->commandPool = $commandPool;
		$this->paymentDataObjectFactory = $paymentDataObjectFactory;
		$this->commerceHubConfig = $commerceHubConfig;
	}

	public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
	{
		return null;
	}

	public function validateForCsrf(RequestInterface $request): ?bool
	{
		return true;
	}

	public function execute()
	{
		$result = $this->resultJsonFactory->create();

		try {
			$rawContent = $this->getRequest()->getContent();
			$data = json_decode($rawContent, true);

			if (!is_array($data)) {
				throw new \Exception('Invalid JSON payload');
			}

			$this->logger->logInfo(1, 'Received ApplePay validation request', ['data' => $data]);

			$sessionId = $data['sessionId'] ?? null;
			$email = $data['email'] ?? null;
			$action = strtolower($data['action'] ?? $this->commerceHubConfig->getPaymentAction());

			if (!$sessionId) {
				return $result->setData(['success' => false, 'message' => 'Missing sessionId']);
			}

			$quote = $this->checkoutSession->getQuote();
			if (!$quote || !$quote->getBillingAddress()) {
				return $result->setData(['success' => false, 'message' => 'Missing quote or billing address']);
			}

			if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$quote->setCustomerEmail($email);
			} else {
				$this->logger->logError(2, 'Invalid email in ApplePay validation', ['email' => $email]);
				return $result->setData([
					'success' => false,
					'message' => 'Invalid email address.',
					'debug_info' => [
						'quote_email' => $quote->getCustomerEmail(),
						'billing_email' => $quote->getBillingAddress() ? $quote->getBillingAddress()->getEmail() : null
					]
				]);
			}

			$order = $this->placeMagentoOrder();
			$payment = $order->getPayment();
			$payment->setMethod('fiserv_applepay');
			$payment->setAdditionalInformation('applepay_session_id', $sessionId);
			$payment->save();

			$paymentDataObject = $this->paymentDataObjectFactory->create($payment, ['order' => $order]);

			$this->logger->logInfo(1, 'ApplePay Command Start', [
				'session_id' => $sessionId,
				'action' => $action,
				'order_id' => $order->getIncrementId()
			]);

			$commandName = match ($action) {
				'authorize_capture' => 'sale',
				'authorize' => 'authorize',
				default => throw new \Exception('Invalid action: ' . $action),
			};

			$commandResult = $this->commandPool->get($commandName)->execute([
				'payment' => $paymentDataObject,
				'amount' => $order->getGrandTotal(),
				'action' => $action
			]);

			$this->logger->logInfo(1, 'ApplePay Command Finish', ['result' => $commandResult]);

			$applePayTxnId = $payment->getLastTransId() ?: $sessionId;
			$transaction = $this->transactionBuilder
				->setPayment($payment)
				->setOrder($order)
				->setTransactionId($applePayTxnId)
				->setAdditionalInformation([Transaction::RAW_DETAILS => ['session_id' => $sessionId]])
				->setFailSafe(true)
				->build(Transaction::TYPE_CAPTURE);

			$this->transactionRepository->save($transaction);

			$order->addStatusHistoryComment('ApplePay transaction ID: ' . $applePayTxnId)
				->setIsCustomerNotified(false)
				->setIsVisibleOnFront(false);
			$order->save();

			return $result->setData([
				'success' => true,
				'order_id' => $order->getIncrementId(),
				'last_transaction_id' => $payment->getLastTransId()
			]);
		} catch (\Throwable $e) {
			$this->logger->logError(1, 'Fatal error in ApplePay Validate', ['exception' => $e]);
			return $result->setData([
				'success' => false,
				'message' => 'Server error: ' . $e->getMessage()
			]);
		}
	}

	protected function placeMagentoOrder()
	{
		$quote = $this->checkoutSession->getQuote();
		$quote->getPayment()->setMethod('fiserv_applepay');
		$quote->collectTotals()->save();

		$order = $this->quoteManagement->submit($quote);
		$order->getPayment()->save();

		$quote->setIsActive(false);
		$this->cartRepository->save($quote);
		$this->checkoutSession->clearQuote();
		$this->cartRepository->delete($quote);

		$this->checkoutSession->setQuoteId(null);
		$this->checkoutSession->setLastQuoteId($quote->getId());
		$this->checkoutSession->setLastSuccessQuoteId($quote->getId());
		$this->checkoutSession->setLastOrderId($order->getId());
		$this->checkoutSession->setLastRealOrderId($order->getIncrementId());

		return $order;
	}
}
