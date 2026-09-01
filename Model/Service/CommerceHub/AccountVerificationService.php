<?php
/**
 * Copyright  Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Fiserv\Payments\Model\Service\CommerceHub;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Helper\MerchantPartnerHelper;
use Fiserv\Payments\Lib\CommerceHub\Model\Address;
use Fiserv\Payments\Lib\CommerceHub\Model\BillingAddress;
use Fiserv\Payments\Lib\CommerceHub\Model\Card;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentSession;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentToken as PaymentTokenSource;
use Fiserv\Payments\Lib\CommerceHub\Model\Phone;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Performs a server-to-server account verification call to Commerce Hub.
 * This service is invoked by the VerifyAccount AJAX controller BEFORE the order is placed.
 * API endpoint: POST /payments-vas/v1/accounts/verification
 */
class AccountVerificationService
{
    const VERIFICATION_ENDPOINT = 'payments-vas/v1/accounts/verification';
    const SOURCE_TYPE_PAYMENT_SESSION = 'PaymentSession';
    const SOURCE_TYPE_PAYMENT_TOKEN = 'PaymentToken';
    const STATE_VERIFIED = 'VERIFIED';

    /** @var Config */
    private $config;

    /** @var ChHttpAdapter */
    private $httpAdapter;

    /** @var CheckoutSession */
    private $checkoutSession;

    /** @var MultiLevelLogger */
    private $logger;

    public function __construct(
        Config $config,
        ChHttpAdapter $httpAdapter,
        CheckoutSession $checkoutSession,
        MultiLevelLogger $logger
    ) {
        $this->config = $config;
        $this->httpAdapter = $httpAdapter;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /** Run account verification for a PaymentSession (card form / ACH). */
    public function verify(string $sessionId): array
    {
        $this->logger->logInfo(2, 'Account verification – starting', "Session: $sessionId");

        $source = new PaymentSession();
        $source->setSourceType(self::SOURCE_TYPE_PAYMENT_SESSION);
        $source->setSessionId($sessionId);

        return $this->sendVerificationRequest($source);
    }

    /**
     * Run account verification for a vault PaymentToken.
     * Commerce Hub requires source.card.expirationMonth and expirationYear.
     */
    public function verifyToken(string $token, string $expirationMonth, string $expirationYear): array
    {
        $this->logger->logInfo(2, 'Account verification (token) – starting');

        $card = new Card();
        $card->setExpirationMonth($expirationMonth);
        $card->setExpirationYear($expirationYear);

        $source = new PaymentTokenSource();
        $source->setSourceType(self::SOURCE_TYPE_PAYMENT_TOKEN);
        $source->setTokenData($token);
        $source->setCard($card);

        return $this->sendVerificationRequest($source);
    }

    /**
     * Build the final payload, call Commerce Hub, and parse the response.
     * Shared by both verify() and verifyToken().
     */
    private function sendVerificationRequest(object $source): array
    {
        $payload = $this->stripNulls([
            'source' => json_decode(json_encode($source), true),
            'billingAddress' => json_decode(json_encode($this->buildBillingAddress()), true),
            'merchantDetails' => json_decode(json_encode($this->buildMerchantDetails()), true),
        ]);

        $this->logger->logDebug(3, 'Account verification – request payload: ' . json_encode($payload, JSON_PRETTY_PRINT));

        try {
            $response = $this->httpAdapter->sendRequest($payload, self::VERIFICATION_ENDPOINT);
        } catch (\Throwable $e) {
            $this->logger->logError(2, 'Account verification – HTTP call error: ' . $e->getMessage());
            return $this->failure('Account verification request failed. Please try again.');
        }

        return $this->parseResponse($response);
    }

    /** Build billing address from the active checkout quote. */
    private function buildBillingAddress(): BillingAddress
    {
        $billing = $this->checkoutSession->getQuote()->getBillingAddress();
        $streetLines = $billing->getStreet();
        $streetFull = implode(' ', array_filter($streetLines));

        $address = new Address([
            'street' => $streetFull,
            'house_number_or_name' => $billing->getStreetLine(1),
            'recipient_name_or_address' => trim($billing->getFirstname() . ' ' . $billing->getLastname()),
            'city' => $billing->getCity(),
            'state_or_province' => $billing->getRegionCode(),
            'postal_code' => $billing->getPostcode(),
            'country' => $billing->getCountryId(),
        ]);

        return new BillingAddress([
            'first_name' => $billing->getFirstname(),
            'middle_name' => $billing->getMiddlename(),
            'last_name' => $billing->getLastname(),
            'address' => $address,
            'phone' => new Phone(['phone_number' => $billing->getTelephone()]),
        ]);
    }

    /** Build MerchantDetails from the plugin configuration. */
    private function buildMerchantDetails(): MerchantDetails
    {
        $details = new MerchantDetails();
        $details->setMerchantId($this->config->getMerchantId());
        $details->setTerminalId($this->config->getTerminalId());
        $details->setMerchantPartner(MerchantPartnerHelper::createMerchantPartner($this->config));
        return $details;
    }

    /** Interpret the raw ChHttpResponse and return a normalised result array. */
    private function parseResponse($response): array
    {
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody(), true);

        $this->logger->logDebug(3, 'Account verification – response HTTP ' . $statusCode . ': ' . json_encode($body, JSON_PRETTY_PRINT));

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->logError(2, 'Account verification – non-2xx HTTP status: ' . $statusCode);
            return $this->failure((string) __('Account verification failed (HTTP %1). Please try a different payment method.', $statusCode));
        }

        $transactionState = strtoupper(trim($body['gatewayResponse']['transactionState']));

        if ($transactionState === self::STATE_VERIFIED) {
            $this->logger->logInfo(2, 'Account verification – VERIFIED');
            return ['success' => true, 'message' => (string) __('Account verified successfully.')];
        }

        $declineMessage = $body['processorResponseDetails']['responseMessage'] ?? $body['gatewayResponse']['transactionState'];

        $this->logger->logError(2, 'Account verification – state: ' . $transactionState . ', reason: ' . $declineMessage);
        return $this->failure((string) __('Account verification failed: %1. Please use a different card or payment method.', $declineMessage));
    }

    private function failure(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }

    /**Recursively remove null and empty-string values so the JSON payload stays clean.*/
    private function stripNulls(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->stripNulls($value);
                if (empty($data[$key])) {
                    unset($data[$key]);
                }
            } elseif ($value === null || $value === '') {
                unset($data[$key]);
            }
        }
        return $data;
    }
}