<?php

declare(strict_types=1);

namespace Fiserv\Payments\Model\Service;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Gateway\Request\CommerceHub\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\AuthDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\SaleDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\SessionSourceDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TokenSourceDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\TransactionInteractionDataBuilder;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Adapter\CommerceHub\OpenRefundRequest;
use Fiserv\Payments\Model\OpenRefund;
use Fiserv\Payments\Model\OpenRefundFactory;
use Fiserv\Payments\Model\System\Utils\PaymentTokenUtil;
use Fiserv\Payments\Observer\CommerceHub\DataAssignObserver;
use Fiserv\Payments\Model\ResourceModel\OpenRefund as OpenRefundResource;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Sales\Api\Data\OrderPaymentExtensionFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order\PaymentFactory;
use Magento\Vault\Api\PaymentTokenManagementInterface;

class OpenRefundService
{
    private const SOURCE_TYPE_TOKEN = 'TOKEN';
    private const SOURCE_TYPE_CARD_HOSTED_FIELDS = 'HOSTED_FIELDS';
    private const ALLOWED_REFUND_REASONS = [
        'CUSTOMER_APPEASEMENT',
        'TIME_SENSITIVE_REFUND',
        'FRAUD_CORRECTION',
        'DUPLICATE_CHARGE',
        'OTHER'
    ];

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

    /** @var PaymentTokenManagementInterface */
    private $tokenManagement;

    /** @var AmountDataBuilder */
    private $amountDataBuilder;

    /** @var TokenSourceDataBuilder */
    private $tokenSourceDataBuilder;

    /** @var SessionSourceDataBuilder */
    private $sessionSourceDataBuilder;

    /** @var AuthDetailsDataBuilder */
    private $authDetailsDataBuilder;

    /** @var SaleDetailsDataBuilder */
    private $saleDetailsDataBuilder;

    /** @var TransactionInteractionDataBuilder */
    private $transactionInteractionDataBuilder;

    /** @var MerchantDetailsDataBuilder */
    private $merchantDetailsDataBuilder;

    /** @var PaymentDataObjectFactory */
    private $paymentDataObjectFactory;

    /** @var PaymentFactory */
    private $orderPaymentFactory;

    /** @var OrderFactory */
    private $orderFactory;

    /** @var OrderPaymentExtensionFactory */
    private $orderPaymentExtensionFactory;

    public function __construct(
        Config $config,
        OpenRefundRequest $openRefundRequest,
        OpenRefundFactory $openRefundFactory,
        OpenRefundResource $openRefundResource,
        AuthSession $authSession,
        MultiLevelLogger $logger,
        PaymentTokenManagementInterface $tokenManagement,
        AmountDataBuilder $amountDataBuilder,
        TokenSourceDataBuilder $tokenSourceDataBuilder,
        SessionSourceDataBuilder $sessionSourceDataBuilder,
        AuthDetailsDataBuilder $authDetailsDataBuilder,
        SaleDetailsDataBuilder $saleDetailsDataBuilder,
        TransactionInteractionDataBuilder $transactionInteractionDataBuilder,
        MerchantDetailsDataBuilder $merchantDetailsDataBuilder,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        PaymentFactory $orderPaymentFactory,
        OrderFactory $orderFactory,
        OrderPaymentExtensionFactory $orderPaymentExtensionFactory
    ) {
        $this->config             = $config;
        $this->openRefundRequest  = $openRefundRequest;
        $this->openRefundFactory  = $openRefundFactory;
        $this->openRefundResource = $openRefundResource;
        $this->authSession        = $authSession;
        $this->logger             = $logger;
        $this->tokenManagement    = $tokenManagement;
        $this->amountDataBuilder = $amountDataBuilder;
        $this->tokenSourceDataBuilder = $tokenSourceDataBuilder;
        $this->sessionSourceDataBuilder = $sessionSourceDataBuilder;
        $this->authDetailsDataBuilder = $authDetailsDataBuilder;
        $this->saleDetailsDataBuilder = $saleDetailsDataBuilder;
        $this->transactionInteractionDataBuilder = $transactionInteractionDataBuilder;
        $this->merchantDetailsDataBuilder = $merchantDetailsDataBuilder;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->orderPaymentFactory = $orderPaymentFactory;
        $this->orderFactory = $orderFactory;
        $this->orderPaymentExtensionFactory = $orderPaymentExtensionFactory;
    }

    /**
     * Create and submit an open refund.
     *
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
            throw new LocalizedException(
                __('Refund amount of %1 exceeds the configured maximum of %2.', $amount, $maxAmount)
            );
	}

	$currencyCode = $this->resolveCurrencyCode($data);

        /** @var OpenRefund $openRefund */
        $openRefund    = $this->openRefundFactory->create();
        $openRefundId  = strtoupper(uniqid('ORF', false));
        $adminUser     = $this->authSession->getUser();

        $openRefund->setData(OpenRefund::KEY_OPEN_REFUND_ID,    $openRefundId);
        $openRefund->setData(OpenRefund::KEY_AMOUNT,            $amount);
        $openRefund->setData(OpenRefund::KEY_CURRENCY_CODE,     $currencyCode);
        $openRefund->setData(OpenRefund::KEY_CUSTOMER_ID,       $data['customer_id'] ?: null);
        $openRefund->setData(OpenRefund::KEY_REFUND_REASON,     $this->resolveRefundReason($data));
        $openRefund->setData(OpenRefund::KEY_NOTES,             (string)($data['notes'] ?? ''));
        $openRefund->setData(OpenRefund::KEY_ORDER_ID,          trim((string)($data['order_id'] ?? '')));
        $openRefund->setData(OpenRefund::KEY_CASE_NUMBER,       trim((string)($data['case_number'] ?? '')));
        $openRefund->setData(OpenRefund::KEY_ADMIN_USER_ID,     $adminUser ? (int)$adminUser->getId() : null);
        $openRefund->setData(OpenRefund::KEY_ADMIN_USERNAME,    $adminUser ? (string)$adminUser->getUserName() : null);
        $openRefund->setData(OpenRefund::KEY_LINKED_IDENTIFIER, (string)($data['linked_identifier'] ?? ''));
        $openRefund->setData(OpenRefund::KEY_STATUS,            OpenRefund::STATUS_DECLINED);

        try {
            $payload  = $this->buildPayload($data, $amount, $currencyCode);
	    $response = $this->openRefundRequest->request($payload);

	    $this->logger->logInfo(
		    1,
		    'Open refund request sending. OpenRefundId: ' . $openRefundId
		    . ', Body: ' . $this->encodeForLog($payload)
	    );

	    $this->logger->logInfo(
		    1,
		    'Open refund response received. OpenRefundId: ' . $openRefundId
		    . ', HttpStatus: ' . (int)($response['_http_status'] ?? 0)
		    . ', Body: ' . $this->encodeForLog($response)
	    );

            $status     = $this->resolveStatus($response);
            $openRefund->setData(OpenRefund::KEY_STATUS, $status);
            $openRefund->setData(
                OpenRefund::KEY_CH_REFUND_ID,
                $response['gatewayResponse']['transactionProcessingDetails']['transactionId'] ?? null
            );

            if ($status === OpenRefund::STATUS_DECLINED) {
                $errorMsg = $this->resolveDeclineMessage($response);
                $openRefund->setData(OpenRefund::KEY_ERROR_MESSAGE, $errorMsg);
                $this->logger->logError(1, 'Open refund declined. OpenRefundId: ' . $openRefundId);
            }

            $this->openRefundResource->save($openRefund);
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $openRefund->setData(OpenRefund::KEY_STATUS,        OpenRefund::STATUS_DECLINED);
            $openRefund->setData(OpenRefund::KEY_ERROR_MESSAGE, $e->getMessage());
            try {
                $this->openRefundResource->save($openRefund);
            } catch (\Throwable $saveEx) {
                $this->logger->logError(1, 'Failed to persist error state: ' . $saveEx->getMessage());
            }
            $this->logger->logError(1, 'Open refund create failed: ' . get_class($e) . ': ' . $e->getMessage());
            throw new LocalizedException(__('Unable to create open refund: %1', $e->getMessage()));
        }

        return $openRefund;
    }

    private function encodeForLog(array $data): string
    {
	    try {
		    return (string)json_encode($data, JSON_UNESCAPED_SLASHES);
	    } catch (\Throwable $e) {
		    return '[unencodable_response]';
	    }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildPayload(array $data, float $amount, string $currencyCode): array
    {
        $captureFlag  = (bool)$this->config->getOpenRefundCaptureFlag();
        $openRefundId = strtoupper(uniqid('ORFSUB', false));

        $sourceType = strtoupper(trim((string)($data['source_type'] ?? self::SOURCE_TYPE_CARD_HOSTED_FIELDS)));
        $paymentSession = (string)($data['payment_session'] ?? '');
        $tokenizedPaymentToken = trim((string)($data['tokenized_payment_token'] ?? ''));

        $buildSubject = $this->createOpenRefundBuildSubject($amount, $currencyCode, $openRefundId, $data);
        $amountPayload = $this->amountDataBuilder->build($buildSubject);
        $sourcePayload = [];

        if (!in_array($sourceType, [
            self::SOURCE_TYPE_TOKEN,
            self::SOURCE_TYPE_CARD_HOSTED_FIELDS
        ], true)) {
            throw new LocalizedException(__('Unsupported payment source selected.'));
        }

        if ($sourceType === self::SOURCE_TYPE_TOKEN) {
            // Resolve vault public_hash → actual gateway token value
            $tokenData = $this->resolveVaultToken($data);
            $this->setPaymentAdditionalInfo($buildSubject, DataAssignObserver::PAYMENT_TOKEN_KEY, $tokenData);
            $sourcePayload = $this->tokenSourceDataBuilder->build($buildSubject);
        } elseif ($sourceType === self::SOURCE_TYPE_CARD_HOSTED_FIELDS && $tokenizedPaymentToken !== '') {
            $this->setPaymentAdditionalInfo($buildSubject, DataAssignObserver::PAYMENT_TOKEN_KEY, $tokenizedPaymentToken);
            $sourcePayload = $this->tokenSourceDataBuilder->build($buildSubject);
        } elseif ($paymentSession !== '') {
            // Hosted-fields path: SDK encrypted the card and returned a session id
            $this->setPaymentAdditionalInfo($buildSubject, DataAssignObserver::SESSION_ID_KEY, $paymentSession);
            $sourcePayload = $this->sessionSourceDataBuilder->build($buildSubject);
        } else {
            throw new LocalizedException(
                __('No payment source provided. Please complete the hosted fields form and retry.')
            );
        }

        $transactionDetailsPayload = $captureFlag
            ? $this->saleDetailsDataBuilder->build($buildSubject)
            : $this->authDetailsDataBuilder->build($buildSubject);

        $transactionInteractionPayload = $this->transactionInteractionDataBuilder->build($buildSubject);

        $merchantDetailsPayload = $this->merchantDetailsDataBuilder->build($buildSubject);

        return [
            AmountDataBuilder::AMOUNT_KEY => $amountPayload[AmountDataBuilder::AMOUNT_KEY],
            'source' => reset($sourcePayload),
            'transactionDetails' => $transactionDetailsPayload['transactionDetails'],
            TransactionInteractionDataBuilder::TXN_INTERACTION_KEY =>
                $transactionInteractionPayload[TransactionInteractionDataBuilder::TXN_INTERACTION_KEY],
            MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY =>
                $merchantDetailsPayload[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY]
        ];
    }

    /**
     * Build a standard gateway subject so existing data builders can be reused without changes.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function createOpenRefundBuildSubject(
        float $amount,
        string $currencyCode,
        string $openRefundId,
        array $data
    ): array {
        $storeId = (int)($data['store_id'] ?? 0);

        $order = $this->orderFactory->create();
        $order->setIncrementId($openRefundId);
        $order->setStoreId($storeId);
        $order->setOrderCurrencyCode($currencyCode);
        $order->setBaseCurrencyCode($currencyCode);

        $payment = $this->orderPaymentFactory->create();
        $payment->setOrder($order);
        $payment->setAdditionalInformation(DataAssignObserver::TOKEN_SOURCE_KEY, 'OPEN_REFUND');
        $payment->setAdditionalInformation(DataAssignObserver::EXP_MONTH_KEY, (string)($data['expiration_month'] ?? '01'));
        $payment->setAdditionalInformation(DataAssignObserver::EXP_YEAR_KEY, (string)($data['expiration_year'] ?? '2099'));
        $payment->setExtensionAttributes($this->orderPaymentExtensionFactory->create());

        return [
            'payment' => $this->paymentDataObjectFactory->create($payment),
            'amount' => $amount,
        ];
    }

    /**
     * @param array<string, mixed> $buildSubject
     * @param string $key
     * @param mixed $value
     */
    private function setPaymentAdditionalInfo(array &$buildSubject, string $key, $value): void
    {
        $paymentDO = $buildSubject['payment'];
        $paymentDO->getPayment()->setAdditionalInformation($key, $value);
    }

    private function resolveCurrencyCode(array $data): string
    {
	    $accepted = $this->getAcceptedCurrencyCodes();
	    $requested = strtoupper(trim((string)($data['currency_code'] ?? '')));

	    if ($requested === '') {
		    return $accepted[0];
	    }

	    if (!in_array($requested, $accepted, true)) {
		    throw new LocalizedException(
			    __('Selected currency is not allowed. Please choose one of: %1', implode(', ', $accepted))
		    );
	    }

	    return $requested;
    }

    private function getAcceptedCurrencyCodes(): array
    {
	    $configured = strtoupper(trim((string)$this->config->getCurrency()));
	    $parts = preg_split('/[\s,]+/', $configured) ?: [];

	    $codes = [];
	    foreach ($parts as $part) {
		    if ($part !== '' && preg_match('/^[A-Z]{3}$/', $part)) {
			    $codes[] = $part;
		    }
	    }

	    $codes = array_values(array_unique($codes));
	    return $codes !== [] ? $codes : ['USD'];
    }

    /**
     * @param array<string, mixed> $data
     * @throws LocalizedException
     */
    private function resolveRefundReason(array $data): string
    {
        $reason = strtoupper(trim((string)($data['refund_reason'] ?? '')));
        if ($reason === '') {
            throw new LocalizedException(__('Refund reason is required.'));
        }

        if (!in_array($reason, self::ALLOWED_REFUND_REASONS, true)) {
            throw new LocalizedException(__('Selected refund reason is not supported.'));
        }

        return $reason;
    }

    /**
     * Resolve a vault public_hash to its gateway_token value.
     *
     * @param array<string, mixed> $data
     * @throws LocalizedException
     */
    private function resolveVaultToken(array $data): string
    {
        $publicHash = (string)($data['payment_token_hash'] ?? '');
        $customerId = (int)($data['customer_id'] ?? 0);

        if ($publicHash !== '' && $customerId > 0) {
            $token = $this->tokenManagement->getByPublicHash($publicHash, $customerId);
            if ($token && $token->getGatewayToken()) {
                return (string)PaymentTokenUtil::getTokenDataFromPersistenceFormat((string)$token->getGatewayToken());
            }
            throw new LocalizedException(__('The selected payment card could not be found for this customer.'));
        }

        throw new LocalizedException(__('Please select a stored payment card.'));
    }

    /**
     * Resolve the status of an open refund based on the API response.
     *
     * @param array<string, mixed> $response
     */
    private function resolveStatus(array $response): string
    {
        $transactionState = strtoupper((string)($response['gatewayResponse']['transactionState'] ?? ''));
        $approvalStatus   = strtoupper((string)(
            $response['paymentReceipt']['processorResponseDetails']['approvalStatus'] ?? ''
        ));

        // Approved only when gateway transaction is captured and processor approves.
        if ($transactionState === 'CAPTURED' && in_array($approvalStatus, ['APPROVED', 'SUCCESS'], true)) {
            return OpenRefund::STATUS_APPROVED;
        }

        // Any other shape is treated as declined in the 2-status model.
        return OpenRefund::STATUS_DECLINED;
    }

    /**
     * Extract decline reason from the most specific CH fields available.
     *
     * @param array<string, mixed> $response
     */
    private function resolveDeclineMessage(array $response): string
    {
        $message = (string)(
            // Commerce Hub API error shape
            $response['error']['message']
            ?? $response['error'][0]['message']
            ?? ''
        );

        return $message !== '' ? $message : (string)__('Refund request failed.');
    }
}

