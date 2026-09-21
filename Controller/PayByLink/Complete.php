<?php
namespace Fiserv\Payments\Controller\PayByLink;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Fiserv\Payments\Model\PayByLink\PaymentLinkHandler;
use Fiserv\Payments\Logger\MultiLevelLogger;

class Complete extends Action implements CsrfAwareActionInterface
{
    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var PaymentLinkHandler
     */
    private $paymentLinkHandler;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    private $checkoutSession;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        PaymentLinkHandler $paymentLinkHandler,
        MultiLevelLogger $logger,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->paymentLinkHandler = $paymentLinkHandler;
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
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
        try {
            $isSuccessful = $this->getRequest()->getParam('isSuccessful');
            $paymentMethod = $this->getRequest()->getParam('paymentMethod');
            $orderId = $this->getRequest()->getParam('pbl_order_ref');

            if ($orderId) {
                $order = $this->paymentLinkHandler->updateOrderStatus(
                    $orderId,
                    $isSuccessful === 'true',
                    $paymentMethod,
                    $this->getRequest()->getParam('transactionId'),
                    $this->getRequest()->getParam('pbl_return_token', ''),
                    (string) $this->checkoutSession->getLastRealOrderId()
                );
                if ($order && $order->getQuoteId()) {
                    $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                    $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                    $this->checkoutSession->setLastOrderId($order->getId());
                    $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                    $this->checkoutSession->setLastOrderStatus($order->getStatus());

                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success', ['_secure' => true]);
                }
            }

            $page = $this->pageFactory->create();

            if ($isSuccessful === 'true') {
                $page->getConfig()->getTitle()->set(__('Payment Processing'));
                $this->getRequest()->setParam('pbl_processing', true);
            } else {
                $page->getConfig()->getTitle()->set(__('Payment Failed'));
            }

            return $page;
        } catch (\Throwable $e) {
            $this->logger->logError(1, "PBL Complete: Unhandled exception in execute: " . $e->getMessage());
            $this->getRequest()->setParam('pbl_processing', true);
            $page = $this->pageFactory->create();
            $page->getConfig()->getTitle()->set(__('Payment Processing'));
            return $page;
        }
    }
}
