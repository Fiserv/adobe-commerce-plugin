<?php
namespace Fiserv\Payments\Model\PayByLink;

use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Model\Order;
use Fiserv\Payments\Model\Adapter\CommerceHub\PaymentLinkSessionRequest;
use Fiserv\Payments\Gateway\Config\PayByLink\Config as PblConfig;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\PayByLink\PaymentLinkErrorResolver;
use Fiserv\Payments\Lib\CommerceHub\Model\BillingAddress;
use Fiserv\Payments\Lib\CommerceHub\Model\ShippingAddress;
use Fiserv\Payments\Lib\CommerceHub\Model\Address;
use Fiserv\Payments\Lib\CommerceHub\Model\Phone;
use Fiserv\Payments\Lib\CommerceHub\Model\Customer;
use Fiserv\Payments\Lib\CommerceHub\Model\OrderData;
use Fiserv\Payments\Lib\CommerceHub\Model\ItemDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\AmountComponents;
use Fiserv\Payments\Lib\CommerceHub\Model\TaxDetails;

/**
 * Encapsulates Pay by Link orchestration so controllers only need to route.
 */
class PaymentLinkHandler
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var PaymentLinkSessionRequest
     */
    private $sessionRequest;

    /**
     * @var PblConfig
     */
    private $pblConfig;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
     * @var ChConfig
     */
    private $chConfig;

    /**
     * @var PaymentLinkErrorResolver
     */
    private $errorResolver;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        PaymentLinkSessionRequest $sessionRequest,
        PblConfig $pblConfig,
        MultiLevelLogger $logger,
        PaymentLinkErrorResolver $errorResolver,
        ChConfig $chConfig,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->orderRepository = $orderRepository;
        $this->sessionRequest = $sessionRequest;
        $this->pblConfig = $pblConfig;
        $this->logger = $logger;
        $this->errorResolver = $errorResolver;
        $this->chConfig = $chConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Regenerate the payment link for an order and persist the new session data.
     *
     * @param int|string $orderId
     * @return array{success: bool, message: string, increment_id: string}
     */
    public function retry($orderId)
    {
        $order = null;
        $payment = null;
        $storeId = null;

        try {
            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();

            $total = (float) $order->getGrandTotal();
            $currency = $order->getOrderCurrencyCode() ?: 'USD';
            $storeId = $order->getStoreId();
            $expiresInMinutes = $this->pblConfig->getExpiryTime($storeId);

            $paymentAction = (string) $payment->getAdditionalInformation('pbl_payment_action')
                ?: (string) $this->pblConfig->getPaymentAction($storeId);
            if ($paymentAction !== MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                $paymentAction = MethodInterface::ACTION_AUTHORIZE;
            }
            $pageId = trim((string) $payment->getAdditionalInformation('pbl_page_id'));
            if ($pageId === '') {
                $pageId = trim((string) $this->pblConfig->getPaymentPageId($storeId));
            }
            $note = trim((string) $payment->getAdditionalInformation('pbl_notes'));

            $orderData = $this->buildOrderData($order);

            $result = $this->sessionRequest->createSession(
                $total,
                $currency,
                $expiresInMinutes,
                $pageId,
                $orderData,
                $order->getIncrementId(),
                $paymentAction,
                $storeId,
                $note
            );
            $response = $result[PaymentLinkSessionRequest::KEY_RESPONSE];

            $payByLinkUrl = $response['checkoutInteractions']['url'] ?? '';
            $sessionId = $response['session']['sessionId'] ?? '';
            $transactionId = $response['gatewayResponse']['transactionProcessingDetails']['transactionId'] ?? '';
            $responsePageId = $response['checkoutInteractions']['pageId'] ?? $pageId;

            $this->applySuccessState($payment, $payByLinkUrl, $sessionId, $transactionId, $paymentAction, (int) $expiresInMinutes);
            $payment->setAdditionalInformation('pbl_page_id', $responsePageId);

            $payment->save();

            // Reset order state to pending if it was canceled/declined
            if ($order->getState() === Order::STATE_CANCELED) {
                $order->setState(Order::STATE_NEW);
                $order->setStatus('pending');
                $order->addCommentToStatusHistory(__('Payment link regenerated. Order reopened.'));
            }

            $this->orderRepository->save($order);

            return [
                'success' => true,
                'message' => (string) __('Payment link refreshed for order %1.', $order->getIncrementId()),
                'increment_id' => (string) $order->getIncrementId(),
            ];
        } catch (\Throwable $e) {
            $resolvedError = $this->errorResolver->resolve($e);

            if ($payment) {
                $this->applyErrorState($payment, $resolvedError);
            }
            if ($order) {
                $this->orderRepository->save($order);
            }

            $this->logger->logError(
                1,
                "Failed to refresh payment link",
                json_encode([
                    'timestamp' => gmdate('c'),
                    'order_id' => (string) $orderId,
                    'merchant_id' => $storeId !== null ? (string) $this->chConfig->getMerchantId($storeId) : '',
                    'failure_type' => $resolvedError['failure_type'],
                    'error_code' => $resolvedError['error_code'],
                    'http_status' => $resolvedError['http_status'],
                    'technical_message' => $resolvedError['technical_message'],
                ], JSON_UNESCAPED_SLASHES)
            );

            return [
                'success' => false,
                'message' => (string) __('%1 %2', $resolvedError['user_message'], $resolvedError['guidance']),
                'increment_id' => $order ? (string) $order->getIncrementId() : '',
            ];
        }
    }

    /**
     * Persist the common "link created" fields on the payment.
     *
     * Removes temporary expiry form fields to prevent serialization issues.
     * Callers may set additional channel-specific fields afterwards.
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param string $payByLinkUrl
     * @param string $sessionId
     * @param string $transactionId
     * @param string $paymentAction
     * @param int $expiresInMinutes
     * @return void
     */
    public function applySuccessState($payment, $payByLinkUrl, $sessionId, $transactionId, $paymentAction, $expiresInMinutes)
    {
        $payment->setAdditionalInformation('pbl_url', $payByLinkUrl);
        $payment->setAdditionalInformation('pbl_session_id', $sessionId);
        $payment->setAdditionalInformation('pbl_transaction_id', $transactionId);
        $payment->setAdditionalInformation('pbl_expires_in_minutes', (int) $expiresInMinutes);
        $payment->setAdditionalInformation('pbl_payment_action', $paymentAction);
        $payment->setAdditionalInformation('pbl_created_at', date('Y-m-d H:i:s'));
        $payment->setAdditionalInformation('pbl_status', 'unpaid');
        $payment->setAdditionalInformation('pbl_error', '');
        $payment->setAdditionalInformation('pbl_error_guidance', '');
        $payment->setAdditionalInformation('pbl_error_code', '');
        $payment->setAdditionalInformation('pbl_error_type', '');

        $this->clearTempExpiryFields($payment);
    }

    /**
     * Persist the error fields on the payment from a resolved error.
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param array $resolvedError
     * @return void
     */
    public function applyErrorState($payment, array $resolvedError)
    {
        $payment->setAdditionalInformation('pbl_error', $resolvedError['user_message']);
        $payment->setAdditionalInformation('pbl_error_guidance', $resolvedError['guidance']);
        $payment->setAdditionalInformation('pbl_error_code', $resolvedError['error_code']);
        $payment->setAdditionalInformation('pbl_error_type', $resolvedError['failure_type']);
        $payment->setAdditionalInformation('pbl_status', 'failed');

        $this->clearTempExpiryFields($payment);
    }

    /**
     * Remove temporary expiry form fields to prevent serialization issues.
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @return void
     */
    private function clearTempExpiryFields($payment)
    {
        $payment->unsAdditionalInformation('pbl_expr_days');
        $payment->unsAdditionalInformation('pbl_expr_hours');
        $payment->unsAdditionalInformation('pbl_expr_minutes');
        $payment->unsAdditionalInformation('pbl_use_default_expiry');
    }

    /**
     * Build order data array with billing/shipping/customer info for session create.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function buildOrderData($order)
    {
        $orderData = [];

        $billing = $order->getBillingAddress();
        if ($billing) {
            $street = $billing->getStreet();

            $billingAddressModel = new Address();
            $billingAddressModel->setStreet(isset($street[0]) ? $street[0] : '');
            $billingAddressModel->setHouseNumberOrName(isset($street[1]) ? $street[1] : '');
            $billingAddressModel->setCity($billing->getCity() ?: '');
            $billingAddressModel->setStateOrProvince($billing->getRegionCode() ?: '');
            $billingAddressModel->setPostalCode($billing->getPostcode() ?: '');
            $billingAddressModel->setCountry($billing->getCountryId() ?: '');

            $billingAddress = new BillingAddress();
            $billingAddress->setFirstName($billing->getFirstname() ?: '');
            $billingAddress->setLastName($billing->getLastname() ?: '');
            $billingAddress->setAddress($billingAddressModel);

            if ($billing->getTelephone()) {
                $phone = new Phone();
                $phone->setPhoneNumber($billing->getTelephone());
                $billingAddress->setPhone($phone);
            }

            $orderData['billingAddress'] = $billingAddress;
        }

        $shipping = $order->getShippingAddress();
        if ($shipping) {
            $street = $shipping->getStreet();

            $shippingAddressModel = new Address();
            $shippingAddressModel->setStreet(isset($street[0]) ? $street[0] : '');
            $shippingAddressModel->setHouseNumberOrName(isset($street[1]) ? $street[1] : '');
            $shippingAddressModel->setCity($shipping->getCity() ?: '');
            $shippingAddressModel->setStateOrProvince($shipping->getRegionCode() ?: '');
            $shippingAddressModel->setPostalCode($shipping->getPostcode() ?: '');
            $shippingAddressModel->setCountry($shipping->getCountryId() ?: '');

            $shippingAddress = new ShippingAddress();
            $shippingAddress->setFirstName($shipping->getFirstname() ?: '');
            $shippingAddress->setLastName($shipping->getLastname() ?: '');
            $shippingAddress->setAddress($shippingAddressModel);

            $orderData['shippingAddress'] = $shippingAddress;
        }

        $customer = new Customer();
        $customer->setEmail($order->getCustomerEmail() ?: '');
        $customer->setFirstName($order->getCustomerFirstname() ?: '');
        $customer->setLastName($order->getCustomerLastname() ?: '');
        $orderData['customer'] = $customer;

        $items = $order->getAllVisibleItems();
        if ($items) {
            $itemDetails = [];
            $itemNumber = 1;
            $orderShippingAmount = round((float) $order->getShippingAmount(), 2);
            foreach ($items as $item) {
                $product = $item->getProduct();
                $imageUrl = '';
                if ($product) {
                    $imageUrl = $product->getSmallImage() ?: '';
                }

                $amountComponents = new AmountComponents();
                $amountComponents->setUnitPrice(round((float) $item->getPrice(), 2));

                // Shipping is an order-level amount; attach it to the first item only
                if ($itemNumber === 1 && $orderShippingAmount > 0) {
                    $amountComponents->setShippingAmount($orderShippingAmount);
                }

                $taxAmount = round((float) $item->getTaxAmount(), 2);
                if ($taxAmount > 0) {
                    $tax = new TaxDetails();
                    $tax->setTaxType('Sales Tax');
                    $tax->setTaxAmount($taxAmount);
                    $amountComponents->setTaxAmounts([$tax]);
                }

                $itemDetail = new ItemDetails();
                $itemDetail->setItemNumber($itemNumber);
                $itemDetail->setProductSku($item->getSku() ?: '');
                $itemDetail->setItemDescription($item->getDescription() ?: $item->getName() ?: '');
                $itemDetail->setQuantity((int) $item->getQtyOrdered());
                $itemDetail->setAmountComponents($amountComponents);

                if ($imageUrl) {
                    $itemDetail->setItemImageUrl($imageUrl);
                }

                $itemDetails[] = $itemDetail;
                $itemNumber++;
            }

            $orderDataModel = new OrderData();
            $orderDataModel->setItemCount(count($itemDetails));
            $orderDataModel->setItemDetails($itemDetails);

            $orderData['orderData'] = $orderDataModel;
        }

        return $orderData;
    }

    /**
     * Update the order status based on the payment outcome reported by the return URL.
     *
     * @param string $incrementId
     * @param bool $isSuccessful
     * @param string|null $paymentMethod Method the shopper used to pay the link (from the return URL).
     * @param string|null $chargeTransactionId Real charge transaction id from the return URL.
     * @return void
     */
    public function updateOrderStatus($incrementId, $isSuccessful, $paymentMethod = null, $chargeTransactionId = null)
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $incrementId)
                ->create();

            $orders = $this->orderRepository->getList($searchCriteria)->getItems();
            $order = reset($orders);

            if (!$order) {
                $this->logger->logError(1, "PBL Complete: Could not find order " . $incrementId);
                return;
            }

            $payment = $order->getPayment();

            if ($isSuccessful) {
                $payment->setAdditionalInformation('pbl_status', 'paid');

                // Persist the method the shopper actually used so that secondary
                // transactions (capture/settle, refund, void/cancel) route to the
                // matching payment method command pool.
                if ($paymentMethod !== null && $paymentMethod !== '') {
                    $payment->setAdditionalInformation('pbl_paid_method', (string) $paymentMethod);
                }

                // Prefer the real charge transaction id reported on the return URL.
                // The id captured at session-create time is NOT a referenceable
                // financial transaction, so referencing it for capture/refund/void
                // fails with CommerceHub error 801. Persist the charge id so all
                // secondary transactions resolve the primary charge correctly.
                if ($chargeTransactionId !== null && $chargeTransactionId !== '') {
                    $payment->setAdditionalInformation('pbl_transaction_id', (string) $chargeTransactionId);
                }

                $pblTxnId = (string) $payment->getAdditionalInformation('pbl_transaction_id');
                if ($pblTxnId !== '') {
                    $payment->setTransactionId($pblTxnId);
                    $payment->setParentTransactionId(null);
                    $payment->setShouldCloseParentTransaction(false);
                    $payment->setIsTransactionClosed(false);
                }

                // Resolve the intent the payment link was created with (sale vs auth).
                $paymentAction = (string) $payment->getAdditionalInformation('pbl_payment_action')
                    ?: (string) $this->pblConfig->getPaymentAction($order->getStoreId());

                if ($paymentAction === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                    // Sale: register the settlement so Magento creates the invoice
                    // and moves the order to processing the same way an online
                    // capture would.
                    $payment->registerCaptureNotification($order->getGrandTotal(), true);
                } else {
                    // Authorize only: register the authorization. The invoice is
                    // created later when the merchant captures from the admin.
                    $payment->registerAuthorizationNotification($order->getGrandTotal());
                }

                $order->addCommentToStatusHistory(__('Payment received via Pay by Link.'));
            } else {
                $payment->setAdditionalInformation('pbl_status', 'declined');
                $order->setState(Order::STATE_CANCELED);
                $order->setStatus('canceled');
                $order->addCommentToStatusHistory(__('Payment declined via Pay by Link.'));
            }

            $this->orderRepository->save($order);
            $this->logger->logInfo(1, "PBL Complete: Order " . $incrementId . " status updated to " . $order->getStatus());
        } catch (\Exception $e) {
            $this->logger->logError(1, "PBL Complete: Error updating order status: " . $e->getMessage());
        }
    }
}
