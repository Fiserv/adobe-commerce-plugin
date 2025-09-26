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
use GuzzleHttp\Client;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Fiserv\Payments\Gateway\Config\PayPal\Config as PayPalConfig;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Fiserv\Payments\Observer\PayPal\DataAssignObserver;
use Fiserv\Payments\Model\Adapter\PayPal\PayPalHttpAdapter;

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

    /** @var PayPalConfig */
    private $payPalConfig;

    /** @var CommerceHubConfig */
    private $commerceHubConfig;

    /** @var PayPalHttpAdapter */
    protected $payPalHttpAdapter;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CartManagementInterface $cartManagement,
        CartManagementInterface $quoteManagement,
        CartRepositoryInterface $cartRepository,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        PayPalHttpAdapter $payPalHttpAdapter,
        PayPalConfig $payPalConfig,
        CommerceHubConfig $commerceHubConfig
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->cartManagement = $cartManagement;
        $this->quoteManagement = $quoteManagement;
        $this->cartRepository = $cartRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->payPalHttpAdapter = $payPalHttpAdapter;
        $this->payPalConfig = $payPalConfig;
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
            $merchentId = $data['merchantId'] ?? null;
            $terminalId = $data['terminalId'] ?? null;
            if (!$orderId) {
                $this->logger->debug('Order ID is missing in request', ['data' => $data]);
                return $result->setData(['success' => false, 'message' => 'Order ID is missing']);
            }

            $quote = $this->checkoutSession->getQuote();
            $payment = $quote->getPayment();
            if ($orderId){
                $payment->setAdditionalInformation('paypal_order_id', $orderId);
                $this->logger->debug('Set PayPal order ID in payment additional information', ['paypal_order_id' => $orderId]);
            }
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
            $payment->save();
            $payment->load($payment->getId());
            $this->logger->debug('Order payment method after update', ['payment_method' => $payment->getId()]);
            $order->save();
            $this->logger->debug('Order placed successfully', ['order_id' => $order->getIncrementId()]);
            return $result->setData(['success' => true, 'order_id' => $order->getIncrementId()]);
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
