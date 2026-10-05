<?php

namespace Fiserv\Payments\Model\Service\CommerceHub;

use Fiserv\Payments\Api\FailedOrder\FailedOrderRepositoryInterface;
use Fiserv\Payments\Api\FailedTransaction\FailedTransactionRepositoryInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\FailedOrderFactory;
use Fiserv\Payments\Model\FailedTransactionFactory;
use Fiserv\Payments\Model\OpenRefund;
use Magento\Customer\Api\CustomerRepositoryInterface;

class OpenRefundDeclineReporter
{
    private const PAYMENT_ACTION = 'OPEN_REFUND';

    /** @var FailedOrderRepositoryInterface */
    private $failedOrderRepository;

    /** @var FailedOrderFactory */
    private $failedOrderFactory;

    /** @var FailedTransactionRepositoryInterface */
    private $failedTransactionRepository;

    /** @var FailedTransactionFactory */
    private $failedTransactionFactory;

    /** @var CustomerRepositoryInterface */
    private $customerRepository;

    /** @var MultiLevelLogger */
    private $logger;

    public function __construct(
        FailedOrderRepositoryInterface $failedOrderRepository,
        FailedOrderFactory $failedOrderFactory,
        FailedTransactionRepositoryInterface $failedTransactionRepository,
        FailedTransactionFactory $failedTransactionFactory,
        CustomerRepositoryInterface $customerRepository,
        MultiLevelLogger $logger
    ) {
        $this->failedOrderRepository = $failedOrderRepository;
        $this->failedOrderFactory = $failedOrderFactory;
        $this->failedTransactionRepository = $failedTransactionRepository;
        $this->failedTransactionFactory = $failedTransactionFactory;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
    }

    /**
     * Record an Open Refund failure for the Declines Dashboard.
     *
     * @param array<string, mixed> $response
     */
    public function reportGatewayDecline(OpenRefund $openRefund, array $response): void
    {
        $this->report(
            $openRefund,
            $response,
            (string)($response['error']['message'] ?? $response['error'][0]['message'] ?? '')
        );
    }

    public function reportException(OpenRefund $openRefund, \Throwable $exception): void
    {
        $this->report(
            $openRefund,
            [],
            $exception->getMessage()
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function report(OpenRefund $openRefund, array $response, string $failureMessage): void
    {
        try {
            $gatewayResponse = is_array($response['gatewayResponse'] ?? null)
                ? $response['gatewayResponse']
                : [];
            $transactionProcessingDetails = is_array($gatewayResponse['transactionProcessingDetails'] ?? null)
                ? $gatewayResponse['transactionProcessingDetails']
                : [];
            $paymentReceipt = is_array($response['paymentReceipt'] ?? null)
                ? $response['paymentReceipt']
                : [];
            $processorResponse = is_array($paymentReceipt['processorResponseDetails'] ?? null)
                ? $paymentReceipt['processorResponseDetails']
                : [];
            $approvedAmount = is_array($paymentReceipt['approvedAmount'] ?? null)
                ? $paymentReceipt['approvedAmount']
                : [];
            $source = is_array($response['source'] ?? null) ? $response['source'] : [];
            $card = is_array($source['card'] ?? null) ? $source['card'] : [];

            $this->createFailedOrder($openRefund, $failureMessage);

            $failedTransaction = $this->failedTransactionFactory->create();
            $failedTransaction->setOrderIncrementId((string)$openRefund->getData(OpenRefund::KEY_OPEN_REFUND_ID));
            $failedTransaction->setDateTime(gmdate('Y-m-d H:i:s'));
            $failedTransaction->setTransactionState((string)($gatewayResponse['transactionState'] ?? 'DECLINED'));
            $failedTransaction->setApprovalStatus(
                $failureMessage !== ''
                    ? $failureMessage
                    : (string)($gatewayResponse['approvalStatus'] ?? $processorResponse['approvalStatus'] ?? 'DECLINED')
            );
            $failedTransaction->setTotalAmount(
                (float)($approvedAmount['total'] ?? $openRefund->getData(OpenRefund::KEY_AMOUNT))
            );
            $failedTransaction->setCurrency((string)(
                $approvedAmount['currency']
                ?? $openRefund->getData(OpenRefund::KEY_CURRENCY_CODE)
            ));
            $failedTransaction->setRemoteIp((string)__('Admin'));
            $failedTransaction->setTransactionId((string)(
                $transactionProcessingDetails['transactionId']
                ?? 'OPEN_REFUND_' . $openRefund->getData(OpenRefund::KEY_OPEN_REFUND_ID)
            ));
            $failedTransaction->setApiTraceId((string)($transactionProcessingDetails['apiTraceId'] ?? ''));
            $failedTransaction->setProcessor((string)($processorResponse['processor'] ?? ''));
            $failedTransaction->setHost((string)($processorResponse['host'] ?? ''));
            $failedTransaction->setExpirationMonth((string)($card['expirationMonth'] ?? ''));
            $failedTransaction->setExpirationYear((string)($card['expirationYear'] ?? ''));
            $failedTransaction->setLast4((string)($card['last4'] ?? ''));
            $failedTransaction->setScheme((string)($card['scheme'] ?? ''));
            $failedTransaction->setNetworkResponseCode((string)($response['networkResponseCode'] ?? ''));
            $failedTransaction->setResponseCode((string)($processorResponse['responseCode'] ?? ''));
            $failedTransaction->setResponseMessage((string)(
                $processorResponse['responseMessage']
                ?? $failureMessage
            ));
            $failedTransaction->setHostResponseMessage((string)($processorResponse['hostResponseMessage'] ?? ''));
            $failedTransaction->setRetrievalReferenceNumber((string)(
                $transactionProcessingDetails['retrievalReferenceNumber']
                ?? $response['transactionDetails']['retrievalReferenceNumber']
                ?? ''
            ));
            $failedTransaction->setPaymentAction(self::PAYMENT_ACTION);

            $this->failedTransactionRepository->save($failedTransaction);
        } catch (\Throwable $exception) {
            $this->logger->logError(
                1,
                'Unable to record declined open refund for the Declines Dashboard. OpenRefundId: '
                . $openRefund->getData(OpenRefund::KEY_OPEN_REFUND_ID)
                . '. Error: '
                . $exception->getMessage()
            );
        }
    }

    private function createFailedOrder(OpenRefund $openRefund, string $failureMessage): void
    {
        $customer = $this->resolveCustomerDetails(
            (int)$openRefund->getData(OpenRefund::KEY_CUSTOMER_ID)
        );
        $failedOrder = $this->failedOrderFactory->create();
        $failedOrder->setOrderIncrementId((string)$openRefund->getData(OpenRefund::KEY_OPEN_REFUND_ID));
        $failedOrder->setDateTime(gmdate('Y-m-d H:i:s'));
        $failedOrder->setOrderState(OpenRefund::STATUS_DECLINED);
        $failedOrder->setBillingAddress('');
        $failedOrder->setShippingAddress('');
        $failedOrder->setCustomerName($customer['name']);
        $failedOrder->setCustomerEmail($customer['email']);
        $failedOrder->setSubtotal((float)$openRefund->getData(OpenRefund::KEY_AMOUNT));
        $failedOrder->setShipping(0.0);
        $failedOrder->setGrandTotal((float)$openRefund->getData(OpenRefund::KEY_AMOUNT));
        $failedOrder->setNotes($failureMessage !== '' ? $failureMessage : (string)__('Open refund declined.'));
        $failedOrder->setItems('[]');
        $failedOrder->setStoreCredit(0.0);
        $failedOrder->setDiscountAmount(0.0);

        $this->failedOrderRepository->save($failedOrder);
    }

    /**
     * @return array{name: string, email: string}
     */
    private function resolveCustomerDetails(int $customerId): array
    {
        if ($customerId <= 0) {
            return ['name' => (string)__('N/A'), 'email' => ''];
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $name = trim($customer->getFirstname() . ' ' . $customer->getLastname());

            return [
                'name' => $name !== '' ? $name : (string)__('Customer #%1', $customerId),
                'email' => (string)$customer->getEmail()
            ];
        } catch (\Throwable $exception) {
            return ['name' => (string)__('Customer #%1', $customerId), 'email' => ''];
        }
    }
}
