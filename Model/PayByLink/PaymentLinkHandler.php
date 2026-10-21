<?php
namespace Fiserv\Payments\Model\PayByLink;

use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Model\Order;
use Fiserv\Payments\Model\Adapter\CommerceHub\PaymentLinkSessionRequest;
use Fiserv\Payments\Model\Adapter\CommerceHub\TransactionInquiryRequest;
use Fiserv\Payments\Gateway\Config\PayByLink\Config as PblConfig;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as ChConfig;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Validator\CommerceHub\TransactionResponseValidator;
use Fiserv\Payments\Model\Service\CommerceHub\FailedTransactionManager;
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

    /**
     * @var TransactionInquiryRequest
     */
    private $transactionInquiry;

    /** @var FailedTransactionManager */
    private $failedTransactionManager;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        PaymentLinkSessionRequest $sessionRequest,
        PblConfig $pblConfig,
        MultiLevelLogger $logger,
        PaymentLinkErrorResolver $errorResolver,
        ChConfig $chConfig,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        TransactionInquiryRequest $transactionInquiry,
        FailedTransactionManager $failedTransactionManager
    ) {
        $this->orderRepository = $orderRepository;
        $this->sessionRequest = $sessionRequest;
        $this->pblConfig = $pblConfig;
        $this->logger = $logger;
        $this->errorResolver = $errorResolver;
        $this->chConfig = $chConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->transactionInquiry = $transactionInquiry;
        $this->failedTransactionManager = $failedTransactionManager;
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
                $this->applyErrorState($payment, $resolvedError, $e);
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
     * @param \Throwable|null $exception Original failure, when recording a session attempt.
     * @return void
     */
    public function applyErrorState($payment, array $resolvedError, ?\Throwable $exception = null)
    {
        $payment->setAdditionalInformation('pbl_error', $resolvedError['user_message']);
        $payment->setAdditionalInformation('pbl_error_guidance', $resolvedError['guidance']);
        $payment->setAdditionalInformation('pbl_error_code', $resolvedError['error_code']);
        $payment->setAdditionalInformation('pbl_error_type', $resolvedError['failure_type']);
        $payment->setAdditionalInformation('pbl_status', 'failed');

        $this->clearTempExpiryFields($payment);

        if ($exception !== null) {
            try {
                $order = $payment->getOrder();
                $response = $exception instanceof PaymentLinkSessionException ? $exception->getResponseBody() : [];

                $this->failedTransactionManager->createFailedTransaction(
                    (string) $order->getIncrementId(),
                    [HttpClient::RESPONSE_KEY => $response],
                    TransactionResponseValidator::RESPONSE_PATHS,
                    'pbl_session_create',
                    null,
                    [
                        'transaction_state' => TransactionResponseValidator::STATE_GATEWAY_ERROR,
                        'total_amount' => $order->getGrandTotal(),
                        'currency' => $order->getOrderCurrencyCode(),
                        'merchant_id' => $this->chConfig->getMerchantId($order->getStoreId()),
                        'country' => $order->getBillingAddress() ? $order->getBillingAddress()->getCountryId() : null,
                    ]
                );
            } catch (\Throwable $e) {
                $this->logger->logError(1, 'Unable to record Pay by Link session failure', json_encode([
                    'order_id' => (string) $payment->getOrder()->getIncrementId(),
                    'exception_type' => get_class($e),
                ]));
            }
        }
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
     * Tolerance (in major currency units) for float rounding noise when
     * comparing the gateway-confirmed approved amount against the expected total.
     */
    private const AMOUNT_TOLERANCE = 0.01;

    /**
     * Update the order status based on the payment outcome reported by the return URL.
     */
    public function updateOrderStatus($incrementId, $isSuccessful, $paymentMethod = null)
    {
        try {
            $order = $this->findOrderByIncrementId($incrementId);
            if (!$order) {
                $this->logger->logError(1, "PBL Complete: Could not find order " . $incrementId);
                return;
            }

            $payment = $order->getPayment();

            // Redirect explicitly reported failure/cancellation — nothing to verify.
            if (!$isSuccessful) {
                $this->applyDeclinedState($order, $payment, __('Payment declined via Pay by Link.'));
                $this->logger->logInfo(1, "PBL Complete: Order " . $incrementId . " status updated to " . $order->getStatus());
                return;
            }

            if ($paymentMethod !== null && $paymentMethod !== '') {
                $payment->setAdditionalInformation('pbl_paid_method', (string) $paymentMethod);
            }
            $paymentAction = (string) $payment->getAdditionalInformation('pbl_payment_action')
                ?: (string) $this->pblConfig->getPaymentAction($order->getStoreId());

            $verification = $this->verifySessionCharge($order, $payment, $incrementId);
            if ($verification === null) {
                return;
            }

            $details = $this->extractInquiryDetails($verification['record']);

            $isApproved = $details['approvalStatus'] === 'APPROVED' && $details['realTransactionId'] !== '';
            if (!$isApproved) {
                $this->declineUnapprovedCharge($order, $payment, $incrementId, $details, $verification['sessionStatus']);
                return;
            }

            $amountCheck = $this->verifyApprovedAmount($order, $verification['record']);
            if (!$amountCheck['isValid']) {
                $this->cancelApprovedChargeDueToAmountMismatch($order, $payment, $incrementId, $details, $amountCheck);
                return;
            }

            $this->finalizeApprovedPayment($order, $payment, $incrementId, $details, $paymentAction);
        } catch (\Exception $e) {
            $this->logger->logError(1, "PBL Complete: Error updating order status: " . $e->getMessage());
        }
    }

    private function findOrderByIncrementId($incrementId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId)
            ->create();

        $orders = $this->orderRepository->getList($searchCriteria)->getItems();
        $order = reset($orders);

        return $order ?: null;
    }


    /**
     * Independently confirm the charge via `v1/inquiries`, keyed by the sessionId
     * persisted at session-create time.
     */
    private function verifySessionCharge(Order $order, $payment, $incrementId)
    {
        $sessionId = (string) $payment->getAdditionalInformation('pbl_session_id');
        if ($sessionId === '') {
            $this->logger->logError(1, "PBL Complete: Missing session id for order " . $incrementId);
            $this->markVerificationFailed($order, $payment, __('Payment could not be verified: missing session id.'));
            return null;
        }

        $record = null;
        $sessionStatus = null;
        try {
            $inquiryResponse = $this->transactionInquiry->executeSessionInquiry($sessionId);
            $sessionStatus = $inquiryResponse['session']['sessionStatus'] ?? null;
            $linkedTransactions = $inquiryResponse['linkedTransactions'] ?? [];
            if (is_array($linkedTransactions) && isset($linkedTransactions[0]) && is_array($linkedTransactions[0])) {
                $record = $linkedTransactions[0];
            }
        } catch (\Throwable $e) {
            $this->logger->logError(1, "PBL Complete: Session inquiry failed for order " . $incrementId . ": " . $e->getMessage());
        }

        if ($record === null) {
            $this->logger->logError(1, "PBL Complete: Could not confirm charge via session inquiry for order " . $incrementId);
            $this->markVerificationFailed($order, $payment, __('Payment could not be verified via session inquiry.'));
            return null;
        }

        return ['record' => $record, 'sessionStatus' => $sessionStatus];
    }

    private function markVerificationFailed(Order $order, $payment, $comment)
    {
        $payment->setAdditionalInformation('pbl_status', 'failed');
        $order->addCommentToStatusHistory($comment);
        $this->orderRepository->save($order);
    }

    /**
     * Pull the fields we care about out of a `linkedTransactions[]` record
     */
    private function extractInquiryDetails(array $record)
    {
        $gatewayResponse = $record['gatewayResponse'] ?? [];
        $processorResponseDetails = $record['processorResponseDetails']
            ?? $record['paymentReceipt']['processorResponseDetails']
            ?? [];

        return [
            'transactionState' => $gatewayResponse['transactionState'] ?? null,
            'approvalStatus' => $gatewayResponse['approvalStatus'] ?? $processorResponseDetails['approvalStatus'] ?? null,
            'realTransactionId' => (string) ($gatewayResponse['transactionProcessingDetails']['transactionId'] ?? ''),
            'processorResponseDetails' => $processorResponseDetails,
        ];
    }


    /**
     * Commerce Hub explicitly did not confirm approval — nothing was charged,
     * so a plain decline + order cancellation is accurate here.
     */
    private function declineUnapprovedCharge(Order $order, $payment, $incrementId, array $details, $sessionStatus)
    {
        $this->applyDeclinedState($order, $payment, __('Payment declined: Commerce Hub did not confirm approval.'));
        $this->logger->logError(1,"PBL Complete: Session inquiry did not confirm approval for order " . $incrementId
        . " (sessionStatus: " . ($sessionStatus ?? 'null') . ", transactionState: " . ($details['transactionState'] ?? 'null')
        . ", approvalStatus: " . ($details['approvalStatus'] ?? 'null') . ")"
        );
    }

    /**
     * Shared "mark declined + cancel order" state mutation, used only where the
     * charge was genuinely never approved (nothing to void at the gateway).
     */
    private function applyDeclinedState(Order $order, $payment, $comment)
    {
        $payment->setAdditionalInformation('pbl_status', 'declined');
        $order->setState(Order::STATE_CANCELED);
        $order->setStatus('canceled');
        $order->addCommentToStatusHistory($comment);
        $this->orderRepository->save($order);
    }

    /**
     * Defense in depth: the gateway-confirmed approved amount must match what we
     * expect to have been charged.
     *
     * Commerce Hub (or the processor) can legitimately add fee components on top
     * of what we submitted at session-create time — e.g. a card-network
     * surcharge or convenience-fee program applied at charge time — so the
     * expected total folds those in rather than doing a strict equality check
     * against the raw Magento grand total, which would otherwise falsely flag
     * every surcharged transaction as tampered.
     *
     * @param Order $order
     * @param array $record
     * @return array{isValid: bool, approvedTotal: float|null, expectedTotal: float}
     */
    private function verifyApprovedAmount(Order $order, array $record)
    {
        $orderTotal = round((float) $order->getGrandTotal(), 2);
        $amountComponents = $record['paymentReceipt']['amountComponents'] ?? [];
        $additionalFees = (float) ($amountComponents['surcharge'] ?? 0) + (float) ($amountComponents['convenienceFee'] ?? 0);
        $expectedTotal = round($orderTotal + $additionalFees, 2);

        $approvedTotal = $record['paymentReceipt']['approvedAmount']['total'] ?? null;

        if ($approvedTotal === null) {
            return ['isValid' => true, 'approvedTotal' => null, 'expectedTotal' => $expectedTotal];
        }

        $approvedTotal = round((float) $approvedTotal, 2);
        $isValid = abs($approvedTotal - $expectedTotal) < self::AMOUNT_TOLERANCE;

        return ['isValid' => $isValid, 'approvedTotal' => $approvedTotal, 'expectedTotal' => $expectedTotal];
    }


    /**
     * The charge WAS approved by Commerce Hub — money may already be on the
     * shopper's card — so this must not be reported as merely "declined" (which
     * implies nothing was ever charged). Actually void/cancel the approved
     * transaction at the gateway, then cancel the order either way so it isn't
     * left in limbo.
     *
     * @param Order $order
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param string $incrementId
     * @param array $details
     * @param array $amountCheck From {@see verifyApprovedAmount()}.
     * @return void
     */
    private function cancelApprovedChargeDueToAmountMismatch(Order $order, $payment, $incrementId, array $details, array $amountCheck)
    {
        $realTransactionId = $details['realTransactionId'];

        $this->logger->logError(1,"PBL Complete: confirmed amount ({$amountCheck['approvedTotal']}) does not match expected total "
            . "({$amountCheck['expectedTotal']}) for order " . $incrementId . " — voiding the approved charge."
        );

        $voided = $this->voidApprovedCharge($payment, $realTransactionId, $incrementId);

        $payment->setAdditionalInformation('pbl_status', 'canceled');
        $payment->setAdditionalInformation('pbl_transaction_id', $realTransactionId);

        $order->setState(Order::STATE_CANCELED);
        $order->setStatus('canceled');
        $order->addCommentToStatusHistory(
            $voided
                ? __('Payment canceled: confirmed amount did not match order total. The approved charge was voided.')
                : __('Payment canceled: confirmed amount did not match order total. WARNING: the approved charge could NOT be voided automatically — manual gateway reconciliation required.')
        );

        $this->orderRepository->save($order);
    }

    /**
     * Attempt to void the real, gateway-approved charge.
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param string $realTransactionId
     * @param string $incrementId
     * @return bool True if the void call completed without throwing.
     */
    private function voidApprovedCharge($payment, $realTransactionId, $incrementId)
    {
        if ($realTransactionId === '') {
            $this->logger->logCritical(
                1,
                "PBL Complete: No real transaction id available — cannot void approved charge for order " . $incrementId
                    . ". Manual gateway reconciliation required."
            );
            return false;
        }

        try {
            // pbl_transaction_id is what CancelRefTxnDataBuilder reads to build the
            // referenceTransactionId sent to Commerce Hub's cancels endpoint.
            $payment->setAdditionalInformation('pbl_transaction_id', $realTransactionId);
            $payment->setTransactionId($realTransactionId);
            $payment->setShouldCloseParentTransaction(false);
            $payment->setIsTransactionClosed(false);

            $payment->void();

            $this->logger->logInfo(1, "PBL Complete: Voided approved charge {$realTransactionId} for order " . $incrementId);
            return true;
        } catch (\Throwable $e) {
            $this->logger->logCritical(
                1,
                "PBL Complete: FAILED to void approved charge {$realTransactionId} for order " . $incrementId
                    . " — manual gateway cancellation required: " . $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Everything checked out — persist the real, confirmed transaction id
     * (required for capture/refund/void) and register the payment with Magento.
     *
     * @param Order $order
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param string $incrementId
     * @param array $details
     * @param string $paymentAction
     * @return void
     */
    private function finalizeApprovedPayment(Order $order, $payment, $incrementId, array $details, $paymentAction)
    {
        $realTransactionId = $details['realTransactionId'];
        $processorResponseDetails = $details['processorResponseDetails'];

        $payment->setAdditionalInformation('pbl_status', 'paid');
        $payment->setAdditionalInformation('pbl_transaction_id', $realTransactionId);
        $payment->setAdditionalInformation('pbl_verified', true);

        if (!empty($processorResponseDetails['approvalCode'])) {
            $payment->setAdditionalInformation('pbl_approval_code', (string) $processorResponseDetails['approvalCode']);
        }

        $payment->setTransactionId($realTransactionId);
        $payment->setShouldCloseParentTransaction(false);
        $payment->setIsTransactionClosed(false);

        if ($paymentAction === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
            $payment->registerCaptureNotification($order->getGrandTotal(), true);
        } else {
            $payment->registerAuthorizationNotification($order->getGrandTotal());
        }

        $order->addCommentToStatusHistory(__('Payment received via Pay by Link.'));

        $this->orderRepository->save($order);
        $this->logger->logInfo(1, "PBL Complete: Order " . $incrementId . " status updated to " . $order->getStatus());
    }
}
