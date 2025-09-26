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
	protected $quoteManagement;
	protected $cartRepository;
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

			$sessionId = $data['sessionId'] ?? null;
			$email = $data['email'] ?? null;
			$action = strtolower($data['action'] ?? $this->commerceHubConfig->getPaymentAction());
			$details = $data['applepay_details'] ?? [];

			if (!$sessionId) {
				return $result->setData(['success' => false, 'message' => 'Missing sessionId']);
			}

			$billingData = $details['billing_address'] ?? [];

			$quote = $this->checkoutSession->getQuote();
			$billingAddress = $quote->getBillingAddress();

			$billingAddress->setFirstname($billingData['firstname'] ?? 'Apple');
			$billingAddress->setLastname($billingData['lastname'] ?? 'Pay');
			$billingAddress->setStreet($billingData['street'] ?? ['1 Infinite Loop']);
			$billingAddress->setCity($billingData['city'] ?? 'Cupertino');
			$billingAddress->setTelephone($billingData['telephone'] ?? '0000000000');
			$billingAddress->setPostcode($billingData['postcode'] ?? '95014');
			$billingAddress->setCountryId($billingData['countryId'] ?? 'US');
			$billingAddress->setRegionId($billingData['regionId'] ?? 34);

			$quote->setBillingAddress($billingAddress);

			if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$quote->setCustomerEmail($email);
			} else {
				return $result->setData([
					'success' => false,
					'message' => 'Invalid email address.',
					'debug_info' => json_encode([
						'quote_email' => $quote->getCustomerEmail(),
						'billing_email' => $billingAddress->getEmail()
					])
				]);
			}

			$order = $this->placeMagentoOrder();
			$payment = $order->getPayment();
			$payment->setMethod('fiserv_applepay');
			$payment->setAdditionalInformation('payment_session', $sessionId);
			$payment->save();

			$paymentDataObject = $this->paymentDataObjectFactory->create($payment, ['order' => $order]);

			$commandName = match ($action) {
				'authorize_capture' => 'sale',
				'authorize' => 'authorize',
				default => throw new \Exception('Invalid action: ' . $action),
			};

			try {
				$commandResult = $this->commandPool->get($commandName)->execute([
					'payment' => $paymentDataObject,
					'amount' => $order->getGrandTotal(),
					'action' => $action,
					'session_id' => $sessionId
				]);
			} catch (\Exception $e) {
				return $result->setData(['success' => false, 'message' => 'Payment gateway error: ' . $e->getMessage()]);
			}

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
			return $result->setData(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
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

	function validateBillingAddress($address): array
	{
		$missing = [];
		if (!$address->getFirstname()) $missing[] = 'firstname';
		if (!$address->getLastname()) $missing[] = 'lastname';
		if (!$address->getStreetLine(1)) $missing[] = 'street';
		if (!$address->getCity()) $missing[] = 'city';
		if (!$address->getTelephone()) $missing[] = 'telephone';
		if (!$address->getPostcode()) $missing[] = 'postcode';
		if (!$address->getCountryId()) $missing[] = 'countryId';
		if (!$address->getRegionId()) $missing[] = 'regionId';
		return $missing;
	}
}
