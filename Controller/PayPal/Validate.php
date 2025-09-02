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
use Magento\Framework\DB\Transaction;
use GuzzleHttp\Client;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;

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
    /** @var Transaction */
    protected $dbTransaction;

    /** @var CommerceHubConfig */
    protected $commerceHubConfig;

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
        Transaction $dbTransaction,
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
        $this->invoiceFactory = $invoiceFactory;
        $this->dbTransaction = $dbTransaction;
        $this->commerceHubConfig = $commerceHubConfig;
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
            $this->checkoutSession->clearQuote();
            $this->cartRepository->delete($quote);
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
        $quote->setIsActive(false);
        $this->cartRepository->save($quote);
        $this->checkoutSession->setQuoteId(null);
        $this->checkoutSession->setLastQuoteId($quote->getId());
        $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
        $this->checkoutSession->setLastOrderId($order->getId());
        return $order;
    }
}
