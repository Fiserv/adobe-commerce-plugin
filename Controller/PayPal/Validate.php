<?php
namespace Fiserv\Payments\Controller\PayPal;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\InvoiceFactory;
use Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface as TransactionManager;
use Magento\Framework\DB\Transaction as DBTransaction;
use GuzzleHttp\Client;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Fiserv\Payments\Gateway\Config\PayPal\Config as PayPalConfig;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Fiserv\Payments\Observer\PayPal\DataAssignObserver;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilder;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;

class Validate extends Action implements CsrfAwareActionInterface
{
    /**
     * Bypass CSRF validation for AJAX requests
     */
    /**
     * Create CSRF validation exception (none needed for this action)
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Validate for CSRF (always true for AJAX endpoint)
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CartManagementInterface
     */
    protected $cartManagement;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var CartManagementInterface
     */
    protected $quoteManagement;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;
    /** @var InvoiceFactory */
    protected $invoiceFactory;
    /** @var DBTransaction */
    protected $dbTransaction;

    /** @var PayPalConfig */
    private $payPalConfig;

    /** @var CommerceHubConfig */
    private $commerceHubConfig;

    /** @var TransactionBuilder */
    protected $transactionBuilder;

    /** @var TransactionRepositoryInterface */
    protected $transactionRepository;

    protected $commandPool;

    protected $paymentDataObjectFactory;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CartManagementInterface $cartManagement,
        CartManagementInterface $quoteManagement,
        CartRepositoryInterface $cartRepository,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        InvoiceFactory $invoiceFactory,
        DBTransaction $dbTransaction,
        PayPalConfig $payPalConfig,
        CommerceHubConfig $commerceHubConfig,
        TransactionBuilder $transactionBuilder,
        TransactionRepositoryInterface $transactionRepository,
        CommandPoolInterface $commandPool,
        PaymentDataObjectFactory $paymentDataObjectFactory
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->cartManagement = $cartManagement;
        $this->quoteManagement = $quoteManagement;
        $this->cartRepository = $cartRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->invoiceFactory = $invoiceFactory;
        $this->dbTransaction = $dbTransaction;
        $this->payPalConfig = $payPalConfig;
        $this->commerceHubConfig = $commerceHubConfig;
        $this->transactionBuilder = $transactionBuilder;
        $this->transactionRepository = $transactionRepository;
        $this->commandPool = $commandPool;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
    }

    /**
     * Execute the PayPal validation and place Magento order if payment is completed.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        try {
            $data = json_decode($this->getRequest()->getContent(), true);
            $this->logger->debug('Received PayPal validation request', ['data' => $data]);
            $orderId = $data['orderId'] ?? null;
            $merchentId = $data['merchantId'] ?? null;
            $terminalId = $data['terminalId'] ?? null;
            $action = strtolower($data['action'] ?? $this->payPalConfig->getPaymentAction());
            if (!$orderId) {
                $this->logger->debug('Order ID is missing in request', ['data' => $data]);
                return $result->setData(['success' => false, 'message' => 'Order ID is missing']);
            }

            $quote = $this->checkoutSession->getQuote();
            $email = $data['email'] ?? null;
            // Log the email address in the quote
            $this->logger->debug('Email address in quote', ['email' => $email]);
            
                if (isset($email)) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $quote->setCustomerEmail($email);
                    }
                }
            
            // If the email is still not set or invalid, return an error
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->error('Invalid email address in PayPal validation', [
                    'email' => $email,
                    'is_empty' => empty($email),
                    'is_valid_email' => filter_var($email, FILTER_VALIDATE_EMAIL),
                    'quote_id' => $quote->getId(),
                    'customer_id' => $quote->getCustomerId(),
                    'is_guest' => $quote->getCustomerIsGuest(),
                    'billing_address_email' => $quote->getBillingAddress() ? $quote->getBillingAddress()->getEmail() : null,
                    'customer_email' => $quote->getCustomerEmail(),
                    'data_email' => $data['email'] ?? null
                ]);
                $result->setData([
                    'success' => false,
                    'message' => 'Invalid email address. Please ensure you have a valid email address in your account or billing address.',
                    'debug_info' => [
                        'user_type' => $quote->getCustomerIsGuest() ? 'guest' : 'logged_in',
                        'quote_email' => $quote->getCustomerEmail(),
                        'billing_email' => $quote->getBillingAddress() ? $quote->getBillingAddress()->getEmail() : null
                    ]
                ]);
                return $result;
            }
            
            $order = $this->placeMagentoOrder();
            $merchentId = $merchentId ?? $this->commerceHubConfig->getMerchantId();
            $terminalId = $terminalId ?? $this->commerceHubConfig->getTerminalId();
            $this->logger->debug('Placing order', ['order_id' => $order->getIncrementId(), 'merchant_id' => $merchentId, 'terminal_id' => $terminalId]);
            $this->logger->debug('Order payment method before update', ['payment_method' => get_class_methods($order)]);
            $payment = $order->getPayment();
            $payment->setMethod('fiserv_paypal');
            $payment->setAdditionalInformation(DataAssignObserver::ORDER_ID, $orderId);
            $payment->save();
            $payment->load($payment->getId());
            $this->logger->debug('Order payment method after update', ['payment_method' => $payment->getId()]);
            $order->save();
            $paypalTxnId = $orderId;

            $paymentDataObject = $this->paymentDataObjectFactory->create($payment, [
                'order' => $order
            ]);
            $this->logger->debug('Payment data object created', [
                'payment_id' => $payment->getId(),
                'payment_method' => $payment->getMethod(),
                'additional_info' => $payment->getAdditionalInformation(),
                'amount' => $payment->getAmountPaid()
            ]);

            $commandSubject = [
                'payment' => $paymentDataObject,
                'amount' => $order->getGrandTotal(),
                'action' => $action,
            ];

            try {
                $this->logger->info('PayPal Command Start', [
                    'order_id' => $orderId,
                    'paypal_order_id' => $payment->getAdditionalInformation('paypal_order_id'),
                    'action' => $action,
                    'payment_id' => $payment->getId(),
                    'amount' => $order->getGrandTotal()
                ]);
                 if ($action === 'authorize_capture') {
                        $commandName = 'sale';
                 } elseif ($action === 'authorize') {
                         $commandName = 'authorize';
                 } else {
                         throw new \Exception('Invalid action: ' . $action);
                 }
                $commandResult = $this->commandPool->get($commandName)->execute($commandSubject);
                $this->logger->info('PayPal Command Finish', [
                    'order_id' => $orderId,
                    'command_result' => $commandResult
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Paypal Command Error', [
                    'order_id' => $orderId,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            // Ensure the order and payment are saved after command execution
            $payment->save();
            $order->save();
            $this->logger->debug('Order placed successfully', ['order_id' => $order->getIncrementId()]);
            $paypalTxnId = $payment->getLastTransId() ?: $paypalTxnId;
            $this->logger->debug('PayPal transaction ID', ['paypal_txn_id' => $paypalTxnId]);
            $this->transactionBuilder->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($paypalTxnId)
                ->setAdditionalInformation([
                    Transaction::RAW_DETAILS => ['order_id' => $orderId]
                ])
                ->setFailSafe(true);
            $transaction = $this->transactionBuilder->build(Transaction::TYPE_CAPTURE);
            $this->transactionRepository->save($transaction);
            $order->addStatusHistoryComment('transaction ID: ' . $paypalTxnId)
                ->setIsCustomerNotified(false)
                ->setIsVisibleOnFront(false);
            $order->save();
            $this->logger->debug('Payment transaction saved', ['transaction_id' => $transaction->getTransactionId()]);
            $this->logger->debug('PayPal transaction ID', ['paypal_txn_id' => $paypalTxnId]);
            return $result->setData(['success' => true, 'order_id' => $order->getIncrementId(), 'last_transaction_id' => $payment->getLastTransId()]);
        } catch (\Throwable $e) {
            // Always return JSON, even on fatal errors
            if (isset($this->logger)) {
                $this->logger->error('Fatal error in PayPal Validate: ' . $e->getMessage(), ['exception' => $e]);
            }
            $result->setData(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        return $result;
    }

    protected function placeMagentoOrder(){
        $quote = $this->checkoutSession->getQuote();
        
        $quote->getPayment()->setMethod('fiserv_paypal');
        $quote->collectTotals()->save();
        $order = $this->quoteManagement->submit($quote);
        $payment = $order->getPayment();
        $payment->save();
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