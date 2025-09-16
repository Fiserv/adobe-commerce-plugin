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
                $this->logger->error('Invalid email address in quote', ['email' => $email]);
                $result->setData(['success' => false, 'message' => 'Invalid email address']);
                return $result;
            }
            
            $order = $this->placeMagentoOrder();
            $merchentId = $merchentId ?? $this->commerceHubConfig->getMerchantId();
            $terminalId = $terminalId ?? $this->commerceHubConfig->getTerminalId();
            $this->logger->debug('Placing order', ['order_id' => $order->getIncrementId(), 'merchant_id' => $merchentId, 'terminal_id' => $terminalId]);
            $payment = $order->getPayment();
            $payment->setMethod('fiserv_paypal');
            $payment->setAdditionalInformation(DataAssignObserver::ORDER_ID, $orderId);
            $payment->save();
            $payment->load($payment->getId());
            $order->save();
            $paypalTxnId = $orderId;

            $paymentDataObject = $this->paymentDataObjectFactory->create($payment, [
                'order' => $order
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
                    'subject' => $commandSubject
                ]);
                $commandName = ($action === 'capture' || $action === 'sale' || $action === 'authorize_capture') ? 'sale' : 'authorize';
                $commandResult = $this->commandPool->get($commandName)->execute($commandSubject);
                $this->logger->info('PayPal Command Finish', [
                    'order_id' => $orderId,
                    'result' => $commandResult
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Paypal Command Error', [
                    'order_id' => $orderId,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            // Get the transaction added by the handler (either auth or capture)
            $transaction = $payment->getAuthorizationTransaction() ?: $payment->getCaptureTransaction();
            if ($transaction) {
                $transaction->setTxnId($paypalTxnId);
                $transaction->setAdditionalInformation(
                    [TransactionManager::RAW_DETAILS => ['paypal_txn_id' => $paypalTxnId]]
                );
                $isCapture = $transaction->getTxnType() === PaymentTransaction::TYPE_CAPTURE;
                $message = $isCapture ? 'PayPal payment captured with Transaction ID: ' . $paypalTxnId : 'PayPal payment authorized with Transaction ID: ' . $paypalTxnId;
                $payment->addTransactionCommentsToOrder($transaction, $message);
                $transaction->setIsClosed($isCapture ? 1 : 0);
                $this->transactionRepository->save($transaction);
                $transactionSave = $this->transactionBuilder->setPayment($payment)
                    ->setOrder($order)
                    ->setTransactionId($paypalTxnId)
                    ->setAdditionalInformation([TransactionManager::RAW_DETAILS => ['paypal_txn_id' => $paypalTxnId]])
                    ->build($transaction->getTxnType());
                $this->transactionRepository->save($transactionSave);
                $payment->setParentTransactionId(null);
                $payment->save();
                $order->save();
            } else {
                $this->logger->error('Transaction not found for order', ['order_id' => $order->getIncrementId(), 'action' => $action]);
            }
            $this->logger->debug('Order placed successfully', ['order_id' => $order->getIncrementId()]);
            $result->setData(['success' => true, 'order_id' => $order->getIncrementId()]);
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
        return $order;
    }
}