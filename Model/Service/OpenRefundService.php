<?php

declare(strict_types=1);

namespace Fiserv\Payments\Model\Service;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Adapter\CommerceHub\OpenRefundRequest;
use Fiserv\Payments\Model\OpenRefund;
use Fiserv\Payments\Model\OpenRefundFactory;
use Fiserv\Payments\Model\ResourceModel\OpenRefund as OpenRefundResource;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\Exception\LocalizedException;

class OpenRefundService
{
    /** @var Config */
    private $config;

    /** @var OpenRefundRequest */
    private $openRefundRequest;

    /** @var OpenRefundFactory */
    private $openRefundFactory;

    /** @var OpenRefundResource */
    private $openRefundResource;

    /** @var AuthSession */
    private $authSession;

    /** @var MultiLevelLogger */
    private $logger;

    public function __construct(
        Config $config,
        OpenRefundRequest $openRefundRequest,
        OpenRefundFactory $openRefundFactory,
        OpenRefundResource $openRefundResource,
        AuthSession $authSession,
        MultiLevelLogger $logger
    ) {
        $this->config = $config;
        $this->openRefundRequest = $openRefundRequest;
        $this->openRefundFactory = $openRefundFactory;
        $this->openRefundResource = $openRefundResource;
        $this->authSession = $authSession;
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $data
     * @return OpenRefund
     * @throws LocalizedException
     */
    public function create(array $data): OpenRefund
    {
        if (!$this->config->isOpenRefundEnabled()) {
            throw new LocalizedException(__('Open refunds are disabled in configuration.'));
        }

        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new LocalizedException(__('Refund amount must be greater than zero.'));
        }

        $maxAmount = (float)$this->config->getOpenRefundMaxAmount();
        if ($maxAmount > 0 && $amount > $maxAmount) {
            throw new LocalizedException(__('Refund amount exceeds the configured limit.'));
        }

        $openRefund = $this->openRefundFactory->create();
        $openRefundId = strtoupper(uniqid('ORF', false));
        $adminUser = $this->authSession->getUser();

        $openRefund->setData('open_refund_id', $openRefundId);
        $openRefund->setData('amount', $amount);
        $openRefund->setData('currency_code', (string)($data['currency_code'] ?? 'USD'));
        $openRefund->setData('customer_id', $data['customer_id'] ?: null);
        $openRefund->setData('notes', (string)($data['notes'] ?? ''));
        $openRefund->setData('masked_card', (string)($data['masked_card'] ?? ''));
        $openRefund->setData('admin_user_id', $adminUser ? (int)$adminUser->getId() : null);
        $openRefund->setData('admin_username', $adminUser ? (string)$adminUser->getUserName() : null);
        $openRefund->setData('linked_identifier', (string)($data['linked_identifier'] ?? ''));
        $openRefund->setData('capture_flag', (int)($data['capture_flag'] ?? 1));
        $openRefund->setData('status', OpenRefund::STATUS_OPEN);

        try {
            $payload = $this->buildPayload($data, $amount);
            $response = $this->openRefundRequest->request($payload);

            $statusCode = (int)($response['_http_status'] ?? 0);
            $status = $this->resolveStatus($response, $statusCode);
            $openRefund->setData('status', $status);
            $openRefund->setData('commercehub_refund_id', $response['gatewayResponse']['transactionProcessingDetails']['transactionId'] ?? null);

            if ($status === OpenRefund::STATUS_DECLINED || $status === OpenRefund::STATUS_ERROR) {
                $openRefund->setData('error_message', $response['gatewayResponse']['gatewayResponseMsg'] ?? __('Refund request failed.'));
                $this->logger->logError(1, 'Open refund declined. OpenRefundId: ' . $openRefundId);
            }

            $this->openRefundResource->save($openRefund);
        } catch (\Exception $e) {
            $openRefund->setData('status', OpenRefund::STATUS_ERROR);
            $openRefund->setData('error_message', $e->getMessage());
            $this->openRefundResource->save($openRefund);
            $this->logger->logError(1, 'Open refund create failed: ' . $e->getMessage());
            throw new LocalizedException(__('Unable to create open refund. %1', $e->getMessage()));
        }

        return $openRefund;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildPayload(array $data, float $amount): array
    {
        $currencyCode = (string)($data['currency_code'] ?? 'USD');
        $captureFlag = isset($data['capture_flag']) ? (bool)$data['capture_flag'] : (bool)$this->config->getOpenRefundCaptureFlag();

        $sourceType = (string)($data['source_type'] ?? 'HOSTED_FIELDS');
        if ($sourceType === 'TOKEN') {
            $source = [
                'sourceType' => 'PaymentToken',
                'paymentToken' => [
                    'tokenData' => (string)($data['payment_token'] ?? '')
                ]
            ];
        } else {
            $source = [
                'sourceType' => 'PaymentCard',
                'encryptionData' => [
                    'encryptionType' => 'RSA',
                    'encryptionTarget' => 'MANUAL',
                    'encryptionBlock' => (string)($data['encryption_block'] ?? ''),
                    'encryptionBlockFields' => (string)($data['encryption_block_fields'] ?? ''),
                    'keyId' => (string)($data['key_id'] ?? '')
                ]
            ];
        }

        return [
            'amount' => [
                'total' => round($amount, 2),
                'currency' => $currencyCode
            ],
            'source' => $source,
            'transactionDetails' => [
                'captureFlag' => $captureFlag
            ],
            'transactionInteraction' => [
                'origin' => 'ECOM',
                'eciIndicator' => 'CHANNEL_ENCRYPTED',
                'posConditionCode' => 'CARD_NOT_PRESENT_ECOM'
            ],
            'merchantDetails' => [
                'merchantId' => $this->config->getMerchantId(),
                'terminalId' => $this->config->getTerminalId()
            ]
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function resolveStatus(array $response, int $statusCode): string
    {
        $transactionState = strtoupper((string)($response['gatewayResponse']['transactionState'] ?? ''));
        $approvalStatus = strtoupper((string)($response['gatewayResponse']['approvalStatus'] ?? ''));

        if (in_array($statusCode, [200, 201, 202], true) && in_array($approvalStatus, ['APPROVED', 'SUCCESS'], true)) {
            return OpenRefund::STATUS_APPROVED;
        }

        if ($transactionState === 'DECLINED' || $approvalStatus === 'DECLINED') {
            return OpenRefund::STATUS_DECLINED;
        }

        if (in_array($statusCode, [200, 201, 202], true)) {
            return OpenRefund::STATUS_OPEN;
        }

        return OpenRefund::STATUS_ERROR;
    }
}

