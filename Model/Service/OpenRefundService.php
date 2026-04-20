<?php

namespace Fiserv\Payments\Model\Service;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Helper\MerchantPartnerHelper;
use Fiserv\Payments\Lib\CommerceHub\Model\Amount;
use Fiserv\Payments\Lib\CommerceHub\Model\Card;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentSession;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentToken;
use Fiserv\Payments\Lib\CommerceHub\Model\RefundRequest;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Fiserv\Payments\Model\OpenRefund;
use Fiserv\Payments\Model\OpenRefundRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class OpenRefundService
{
    const REFUNDS_ENDPOINT = 'payments/v1/refunds';
    const PAYMENT_TOKEN_SOURCE_TYPE = 'PaymentToken';
    const PAYMENT_SESSION_SOURCE_TYPE = 'PaymentSession';
    const TS_SUFFIX_PATTERN = '/\$\$TS\$\$=.*/';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ChHttpAdapter
     */
    private $httpAdapter;

    /**
     * @var OpenRefundRepository
     */
    private $openRefundRepository;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    public function __construct(
        Config $config,
        ChHttpAdapter $httpAdapter,
        OpenRefundRepository $openRefundRepository,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        MultiLevelLogger $logger
    ) {
        $this->config = $config;
        $this->httpAdapter = $httpAdapter;
        $this->openRefundRepository = $openRefundRepository;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * Submit an open refund: validate → resolve source → build request → save → call CH → persist result.
     *
     * @param OpenRefund $openRefund
     * @param array $formData
     * @throws LocalizedException
     */
    public function submit(OpenRefund $openRefund, array $formData): void
    {
        // Step 1: Enforce transaction limit
        $limit = (float)($this->config->getValue('open_refund_transaction_limit') ?? 0);
        if ($limit > 0 && (float)$openRefund->getAmount() > $limit) {
            throw new LocalizedException(
                __('Refund amount exceeds the configured transaction limit of %1.', $limit)
            );
        }

        // Step 2: Resolve payment source
        $paymentSourceType = $formData['payment_source_type'] ?? 'vault';
        if ($paymentSourceType === 'new_card') {
            $source = $this->buildSessionSource($formData);
        } else {
            $source = $this->buildVaultSource($formData, (int)$openRefund->getCustomerId());
        }

        // Step 3: Build RefundRequest (NO referenceTransactionDetails)
        $refundRequest = $this->buildRefundRequest($openRefund, $source);

        // Step 4: Save record as pending before API call
        $openRefund->setStatus(OpenRefund::STATUS_PENDING);
        $this->openRefundRepository->save($openRefund);

        // Step 5: POST to CommerceHub
        try {
            $chResponse = $this->httpAdapter->sendRequest($refundRequest, self::REFUNDS_ENDPOINT);
        } catch (\Exception $e) {
            $this->logger->logCritical(1, 'OpenRefundService: HTTP request to CommerceHub failed');
            $this->logger->logCritical(2, $e->getMessage());
            $openRefund->setStatus(OpenRefund::STATUS_FAILED);
            $this->openRefundRepository->save($openRefund);
            throw new LocalizedException(__('Communication with the payment gateway failed. Please try again.'));
        }

        // Step 6: Parse response
        $statusCode = $chResponse->getStatusCode();
        $responseBody = json_decode($chResponse->getBody(), true) ?? [];

        $transactionState = $responseBody['gatewayResponse']['transactionState'] ?? '';

        if ($statusCode >= 200 && $statusCode < 300 && strtoupper($transactionState) === 'CAPTURED') {
            $transactionId = $responseBody['gatewayResponse']['transactionProcessingDetails']['transactionId'] ?? '';
            $maskedCard = $responseBody['source']['card']['last4'] ?? '';

            $openRefund->setStatus(OpenRefund::STATUS_SUCCESS);
            $openRefund->setTransactionId($transactionId);
            $openRefund->setMaskedCard($maskedCard);
            $this->openRefundRepository->save($openRefund);
        } else {
            $errorMessage = $responseBody['error'][0]['message'] ?? ($responseBody['gatewayResponse']['transactionState'] ?? 'Unknown error');
            $openRefund->setStatus(OpenRefund::STATUS_FAILED);
            $this->openRefundRepository->save($openRefund);

            $this->logger->logError(1, 'OpenRefundService: CH returned non-CAPTURED state: ' . $transactionState . ' (HTTP ' . $statusCode . ')');

            throw new LocalizedException(
                __('Refund was declined by the payment gateway: %1', $errorMessage)
            );
        }
    }

    /**
     * Build a PaymentSession source from a hosted-fields session token.
     *
     * @return PaymentSession
     */
    private function buildSessionSource(array $formData)
    {
        $sessionToken = $formData['hosted_field_token'] ?? '';
        if (empty($sessionToken)) {
            throw new LocalizedException(__('Hosted field session token is missing. Please re-enter card details.'));
        }

        $source = new PaymentSession();
        $source->setSessionId($sessionToken);

        return $source;
    }

    /**
     * Build a PaymentToken source from a stored vault token.
     *
     * @return PaymentToken
     */
    private function buildVaultSource(array $formData, int $customerId)
    {
        $publicHash = $formData['vault_token_hash'] ?? '';
        if (empty($publicHash)) {
            throw new LocalizedException(__('Please select a saved card.'));
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('public_hash', $publicHash)
            ->addFilter('customer_id', $customerId)
            ->addFilter('is_active', 1)
            ->create();

        $tokens = $this->paymentTokenRepository->getList($searchCriteria)->getItems();
        if (empty($tokens)) {
            throw new LocalizedException(__('The selected payment token could not be found or is no longer active.'));
        }

        $vaultToken = reset($tokens);
        $gatewayToken = $vaultToken->getGatewayToken();

        // Strip $$TS$$=... suffix that Magento appends
        $tokenData = preg_replace(self::TS_SUFFIX_PATTERN, '', $gatewayToken);

        $details = json_decode($vaultToken->getTokenDetails() ?: '{}', true);
        $tokenSource = $details['tokenSource'] ?? '';
        $expDateParts = isset($details['expirationDate']) ? explode('/', $details['expirationDate']) : [];
        $expMonth = $expDateParts[0] ?? ($details['expMonth'] ?? '');
        $expYear  = $expDateParts[1] ?? ($details['expYear'] ?? '');
        $nameOnCard = $details['nameOnCard'] ?? '';

        $card = new Card();
        if ($expMonth) {
            $card->setExpirationMonth($expMonth);
        }
        if ($expYear) {
            $card->setExpirationYear($expYear);
        }
        if ($nameOnCard) {
            $card->setNameOnCard($nameOnCard);
        }

        $paymentToken = new PaymentToken();
        $paymentToken->setSourceType(self::PAYMENT_TOKEN_SOURCE_TYPE);
        $paymentToken->setTokenData($tokenData);
        if ($tokenSource) {
            $paymentToken->setTokenSource($tokenSource);
        }
        $paymentToken->setDeclineDuplicates(false);
        $paymentToken->setCard($card);

        return $paymentToken;
    }

    /**
     * Build the RefundRequest model — critically WITHOUT referenceTransactionDetails.
     */
    private function buildRefundRequest(OpenRefund $openRefund, $source): RefundRequest
    {
        // Amount
        $amount = new Amount();
        $amount->setTotal((string)$openRefund->getAmount());
        $amount->setCurrency($openRefund->getCurrencyCode());

        // MerchantDetails
        $merchantDetails = new MerchantDetails();
        $merchantDetails->setMerchantId($this->config->getMerchantId());
        $merchantDetails->setTerminalId($this->config->getTerminalId());
        $merchantPartner = MerchantPartnerHelper::createMerchantPartner($this->config);
        $merchantDetails->setMerchantPartner($merchantPartner);

        // TransactionDetails
        $transactionDetails = new TransactionDetails();
        $transactionDetails->setCaptureFlag($this->config->isOpenRefundCaptureFlag());
        $transactionDetails->setCreateToken(false);

        $referenceTransactionId = $openRefund->getReferenceTransactionId();
        if (!empty($referenceTransactionId)) {
            $transactionDetails->setMerchantOrderId($referenceTransactionId);
        }

        // Build request — no setReferenceTransactionDetails
        $refundRequest = new RefundRequest();
        $refundRequest->setSource($source);
        $refundRequest->setAmount($amount);
        $refundRequest->setTransactionDetails($transactionDetails);
        $refundRequest->setMerchantDetails($merchantDetails);

        return $refundRequest;
    }
}