<?php
namespace Fiserv\Payments\Model\Service\CommerceHub;

use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider;
use Fiserv\Payments\Model\System\Utils\PaymentTokenUtil;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class OpenRefundVaultTokenService
{
    /** @var PaymentTokenFactoryInterface */
    private $paymentTokenFactory;

    /** @var PaymentTokenRepositoryInterface */
    private $paymentTokenRepository;

    /** @var EncryptorInterface */
    private $encryptor;

    /** @var MultiLevelLogger */
    private $logger;

    public function __construct(
        PaymentTokenFactoryInterface $paymentTokenFactory,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        EncryptorInterface $encryptor,
        MultiLevelLogger $logger
    ) {
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->encryptor = $encryptor;
        $this->logger = $logger;
    }

    /**
     * Persist a newly tokenized CommerceHub card into Magento Vault.
     *
     * @param array<string, mixed> $tokenResponse
     * @param int $customerId
     * @return PaymentTokenInterface
     * @throws LocalizedException
     */
    public function persistTokenizedCard(
        array $tokenResponse,
        int $customerId
    ): PaymentTokenInterface {
        if ($customerId <= 0) {
            throw new LocalizedException(
                __('A valid customer is required to store the payment card.')
            );
        }

        $paymentTokenData = $tokenResponse['paymentTokens'][0] ?? [];
        $cardData = $tokenResponse['source']['card'] ?? [];

        if (!is_array($paymentTokenData)) {
            throw new LocalizedException(
                __('Tokenized card data was not returned by Commerce Hub.')
            );
        }

        if (!is_array($cardData)) {
            $cardData = [];
        }

        $tokenData = trim(
            (string)($paymentTokenData['tokenData'] ?? '')
        );
        $tokenSource = trim(
            (string)($paymentTokenData['tokenSource'] ?? '')
        );
        $tokenResponseCode = trim(
            (string)($paymentTokenData['tokenResponseCode'] ?? '')
        );
        $tokenResponseDescription = trim(
            (string)($paymentTokenData['tokenResponseDescription'] ?? '')
        );

        if ($tokenData === '' || $tokenSource === '') {
            throw new LocalizedException(
                __('Tokenized card data was not returned by Commerce Hub.')
            );
        }

        $expirationMonth = str_pad(
            trim((string)($cardData['expirationMonth'] ?? '')),
            2,
            '0',
            STR_PAD_LEFT
        );
        $expirationYear = trim(
            (string)($cardData['expirationYear'] ?? '')
        );
        $lastFour = trim(
            (string)($cardData['last4'] ?? '')
        );
        $cardType = trim((string)(
            $cardData['scheme']
            ?? $cardData['cardType']
            ?? ''
        ));
        $nameOnCard = trim(
            (string)($cardData['nameOnCard'] ?? '')
        );

        if ($expirationMonth === '00' || $expirationYear === '') {
            throw new LocalizedException(
                __('Tokenized card expiration details were not returned by Commerce Hub.')
            );
        }

        if ($lastFour === '') {
            throw new LocalizedException(
                __('Tokenized card number details were not returned by Commerce Hub.')
            );
        }

        $gatewayToken = PaymentTokenUtil::formatTokenDataForPersistence(
            $tokenData
        );

        $tokenDetails = [
            'type' => $cardType,
            'maskedCC' => '************' . $lastFour,
            'expirationDate' => $expirationMonth . '/' . $expirationYear,
            'tokenSource' => $tokenSource,
            'tokenResponseCode' => $tokenResponseCode,
            'tokenResponseDescription' => $tokenResponseDescription
        ];

        if ($nameOnCard !== '') {
            $tokenDetails['nameOnCard'] = $nameOnCard;
        }

        $encodedDetails = json_encode($tokenDetails);

        if ($encodedDetails === false) {
            throw new LocalizedException(
                __('Unable to encode the stored payment card details.')
            );
        }

        /** @var PaymentTokenInterface $vaultPaymentToken */
        $vaultPaymentToken = $this->paymentTokenFactory->create(
            PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
        );

        $vaultPaymentToken->setCustomerId($customerId);
        $vaultPaymentToken->setPaymentMethodCode(
            ConfigProvider::CODE
        );
        $vaultPaymentToken->setGatewayToken($gatewayToken);
        $vaultPaymentToken->setTokenDetails($encodedDetails);
        $vaultPaymentToken->setExpiresAt(
            $this->resolveTokenExpirationDate(
                $expirationMonth,
                $expirationYear
            )
        );
        $vaultPaymentToken->setIsActive(true);
        $vaultPaymentToken->setIsVisible(true);
        $vaultPaymentToken->setPublicHash(
            $this->generatePublicHash($vaultPaymentToken)
        );

        $savedToken = $this->paymentTokenRepository->save(
            $vaultPaymentToken
        );

        if (!$savedToken->getPublicHash()) {
            throw new LocalizedException(
                __('The stored payment token public hash was not generated.')
            );
        }

        $this->logger->logInfo(
            1,
            'Open refund payment token stored in Magento Vault. CustomerId: '
            . $customerId
            . ', PublicHash: '
            . $savedToken->getPublicHash()
        );

        return $savedToken;
    }

    private function generatePublicHash(
        PaymentTokenInterface $paymentToken
    ): string {
        $hashKey =
            (string)$paymentToken->getCustomerId()
            . (string)$paymentToken->getPaymentMethodCode()
            . (string)$paymentToken->getType()
            . (string)$paymentToken->getGatewayToken();

        return $this->encryptor->getHash($hashKey);
    }

    private function resolveTokenExpirationDate(
        string $expirationMonth,
        string $expirationYear
    ): string {
        $month = (int)$expirationMonth;
        $year = (int)$expirationYear;

        if (
            $month < 1
            || $month > 12
            || $year < 1
        ) {
            throw new LocalizedException(
                __('The tokenized card expiration date is invalid.')
            );
        }

        $expirationDate = \DateTimeImmutable::createFromFormat(
            '!Y-n-j H:i:s',
            $year . '-' . $month . '-1 23:59:59'
        );

        if (!$expirationDate) {
            throw new LocalizedException(
                __('The tokenized card expiration date is invalid.')
            );
        }

        return $expirationDate
            ->modify('last day of this month')
            ->format('Y-m-d H:i:s');
    }
}
