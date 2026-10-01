<?php
namespace Fiserv\Payments\Controller\PayByLink;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\PageFactory;
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

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        PaymentLinkHandler $paymentLinkHandler,
        MultiLevelLogger $logger
    ) {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->paymentLinkHandler = $paymentLinkHandler;
        $this->logger = $logger;
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

            // Update order status based on payment result
            if ($orderId) {
                $this->paymentLinkHandler->updateOrderStatus(
                    $orderId,
                    $isSuccessful === 'true',
                    $paymentMethod
                );
            } else {
                $this->logger->logError(1, "PBL Complete: Cannot make an order - missing order ID");
            }

            $page = $this->pageFactory->create();

            if ($isSuccessful === 'true') {
                $page->getConfig()->getTitle()->set(__('Payment Successful'));
            } else {
                $page->getConfig()->getTitle()->set(__('Payment Failed'));
            }

            return $page;
        } catch (\Exception $e) {
            $this->logger->logError(1, "PBL Complete: Unhandled exception in execute: " . $e->getMessage());
            $page = $this->pageFactory->create();
            $page->getConfig()->getTitle()->set(__('Payment Processing'));
            return $page;
        }
    }
}
