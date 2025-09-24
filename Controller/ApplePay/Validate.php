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

			$this->logger->logInfo(1, 'Received ApplePay validation request', json_encode(['data' => $data]));

			$sessionId = $data['sessionId'] ?? null;

			$this->logger->logInfo(1, 'ApplePay Validate - Extracted sessionId', json_encode(['sessionId_from_payload' => $sessionId]));

			$email = $data['email'] ?? null;
			$action = strtolower($data['action'] ?? $this->commerceHubConfig->getPaymentAction());
			$details = $data['applepay_details'] ?? [];

			if (!$sessionId) {
				return $result->setData(['success' => false, 'message' => 'Missing sessionId']);
			}

			if (empty($details)) {
				return $result->setData(['success' => false, 'message' => 'Missing ApplePay details']);
			}

			$quote = $this->checkoutSession->getQuote();
			$billingAddress = $quote->getBillingAddress();

			if (!$quote || !$billingAddress) {
				return $result->setData(['success' => false, 'message' => 'Missing quote or billing address']);
			}

			$billingData = $details['billing_address'] ?? [];

			if (!empty($billingData)) {
				$billingAddress->setFirstname($billingData['firstname'] ?? 'Apple');
				$billingAddress->setLastname($billingData['lastname'] ?? 'Pay');
				$billingAddress->setStreet([$billingData['street'] ?? '1 Infinite Loop']);
				$billingAddress->setCity($billingData['city'] ?? 'Cupertino');
				$billingAddress->setTelephone($billingData['telephone'] ?? '0000000000');
				$billingAddress->setPostcode($billingData['postcode'] ?? '95014');
				$billingAddress->setCountryId($billingData['countryId'] ?? 'US');
				if (!empty($billingData['regionId'])) {
					$billingAddress->setRegionId($billingData['regionId']);
				}
				$quote->setBillingAddress($billingAddress);
				$this->logger->logInfo(1, 'Billing address populated from ApplePay', json_encode($billingData));
			} else {
				$billingAddress->setFirstname('Test');
				$billingAddress->setLastname('User');
				$billingAddress->setStreet(['123 Test Street']);
				$billingAddress->setCity('Testville');
				$billingAddress->setTelephone('1234567890');
				$billingAddress->setPostcode('12345');
				$billingAddress->setCountryId('US');
				$billingAddress->setRegionId(34); // New Jersey fallback
				$quote->setBillingAddress($billingAddress);
				$this->logger->logWarning(2, 'Billing address fallback used', json_encode(['reason' => 'Missing billing_address in ApplePay details']));
			}

			$this->logger->logInfo(1, 'Billing address region info', json_encode([
				'regionId' => $billingAddress->getRegionId(),
				'region' => $billingAddress->getRegion(),
				'countryId' => $billingAddress->getCountryId()
			]));

			$missingFields = $this->validateBillingAddress($billingAddress);
			if (!empty($missingFields)) {
				$this->logger->logError(1, 'Incomplete billing address', json_encode(['missing_fields' => $missingFields]));
				return $result->setData(['success' => false, 'message' => 'Please check the billing address information. Missing: ' . implode(',', $missingFields)]);
			}

			if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$quote->setCustomerEmail($email);
			} else {
				$this->logger->logError(2, 'Invalid email in ApplePay validation', json_encode(['email' => $email]));
				return $result->setData(['success' => false, 'message' => 'Invalid email address.', 'debug_info' => json_encode([
					'quote_email' => $quote->getCustomerEmail(),
					'billing_email' => $billingAddress->getEmail()
				])]);
			}

			$order = $this->placeMagentoOrder();
			$payment = $order->getPayment();
			$payment->setMethod('fiserv_applepay');
			$payment->setAdditionalInformation('applepay_session_id', $sessionId);
			$payment->setAdditionalInformation('session_id', $sessionId);
			$payment->setAdditionalInformation('applepay_details', $details);
			$payment->save();

			$paymentDataObject = $this->paymentDataObjectFactory->create($payment, ['order' => $order]);

			$this->logger->logInfo(1, 'ApplePay Command Start', json_encode([
				'session_id' => $sessionId,
				'action' => $action,
				'order_id' => $order->getIncrementId()
			]));

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
				$this->logger->logError(1, 'ApplePay Command Execution Error', json_encode(['error' => $e->getMessage()]));
				return $result->setData(['success' => false, 'message' => 'Payment gateway error: ' . $e->getMessage()]);
			}

			$this->logger->logInfo(1, 'ApplePay Command Finish', json_encode(['result' => $commandResult]));

			$applePayTxnId = $payment->getLastTransId() ?: $sessionId;
			if (!$payment->getLastTransId()) {
				$this->logger->logWarning(2, 'Missing transaction ID, falling back to sessionId', json_encode(['session_id' => $sessionId]));
			}

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
			$this->logger->logError(1, 'Fatal error in ApplePay Validate', json_encode(['error' => $e->getMessage()]));
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

	protected function validateBillingAddress($address): array
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
