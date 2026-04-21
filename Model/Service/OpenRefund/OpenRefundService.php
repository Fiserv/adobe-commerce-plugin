<?php
namespace Fiserv\Payments\Model\Service\OpenRefund;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Helper\MerchantPartnerHelper;
use Fiserv\Payments\Lib\CommerceHub\Model\Amount;
use Fiserv\Payments\Lib\CommerceHub\Model\Card;
use Fiserv\Payments\Lib\CommerceHub\Model\Customer;
use Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails;
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

/**
 * Service responsible for submitting open refunds to CommerceHub.
 */
class OpenRefundService
{
	const REFUNDS_ENDPOINT = 'payments/v1/refunds';
	const PAYMENT_TOKEN_SOURCE_TYPE = 'PaymentToken';
	const TS_SUFFIX_PATTERN = '/\$\$TS\$\$=.*/';

	public function __construct(
		private readonly Config $config,
		private readonly ChHttpAdapter $httpAdapter,
		private readonly OpenRefundRepository $openRefundRepository,
		private readonly PaymentTokenRepositoryInterface $paymentTokenRepository,
		private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
		private readonly MultiLevelLogger $logger
	) {}

	/**Submit an open refund: validate → resolve source → build request → save → call CH → persist result.*/
	public function submit(OpenRefund $openRefund, array $formData): void
	{
		$sourceType = $formData['payment_source_type'] ?? 'vault';

		$this->logger->logInfo(1, 'Initiating open refund');
		$this->logger->logInfo(2, 'Open Refund Amount: ' . $openRefund->getAmount() . ' USD');
		$this->logger->logInfo(2, 'Open Refund Customer ID: ' . ($openRefund->getCustomerId() ?? 'n/a'));
		$this->logger->logInfo(2, 'Open Refund Payment source type: ' . $sourceType);

		$limit = (float)($this->config->getValue('open_refund_transaction_limit') ?? 0);
		if ($limit > 0 && (float)$openRefund->getAmount() > $limit) {
			throw new LocalizedException(__('Refund amount exceeds the configured transaction limit of %1.', $limit));
		}

		$source = $sourceType === 'new_card'
			? $this->buildTokenDataSource($formData)
			: $this->buildVaultSource($formData, (int)$openRefund->getCustomerId());

		$refundRequest = $this->buildRefundRequest($openRefund, $source);

		$openRefund->setStatus(OpenRefund::STATUS_PENDING);
		$this->openRefundRepository->save($openRefund);

		$this->logger->logInfo(1, 'Open Refund Sending open refund request to CommerceHub');
		$this->logger->logDebug(3, 'Open Refund Request payload: ' . json_encode(json_decode(json_encode($refundRequest), true), JSON_PRETTY_PRINT));

		try {
			$chResponse = $this->httpAdapter->sendRequest($refundRequest, self::REFUNDS_ENDPOINT);
		} catch (\Exception $e) {
			$this->logger->logCritical(1, 'Open Refund HTTP request to CommerceHub failed');
			$this->logger->logCritical(2, $e->getMessage());
			$openRefund->setStatus(OpenRefund::STATUS_FAILED);
			$this->openRefundRepository->save($openRefund);
			throw new LocalizedException(__('Communication with the payment gateway failed. Please try again.'));
		}

		$statusCode   = $chResponse->getStatusCode();
		$responseBody = json_decode($chResponse->getBody(), true) ?? [];
		$transactionState = $responseBody['gatewayResponse']['transactionState'] ?? '';

		$this->logger->logDebug(3, 'Open Refund Response (HTTP ' . $statusCode . '): ' . json_encode($responseBody, JSON_PRETTY_PRINT));

		if ($statusCode >= 200 && $statusCode < 300 && strtoupper($transactionState) === 'CAPTURED') {
			$transactionId = $responseBody['gatewayResponse']['transactionProcessingDetails']['transactionId'] ?? '';
			$maskedCard    = $responseBody['source']['card']['last4'] ?? '';

			$openRefund->setStatus(OpenRefund::STATUS_SUCCESS);
			$openRefund->setTransactionId($transactionId);
			$openRefund->setMaskedCard($maskedCard);
			$this->openRefundRepository->save($openRefund);

			$this->logger->logInfo(2, 'Open Refund Transaction ID: ' . $transactionId);
		} else {
			$errorMessage = $responseBody['error'][0]['message']
				?? $responseBody['errors'][0]['message']
				?? $transactionState
				?? 'Unknown error';

			$openRefund->setStatus(OpenRefund::STATUS_FAILED);
			$this->openRefundRepository->save($openRefund);

			$this->logger->logError(1, 'Open Refund CH returned non-CAPTURED state: ' . $transactionState . ' (HTTP ' . $statusCode . ')');
			$this->logger->logError(2, 'Open Refund Error message: ' . $errorMessage);

			throw new LocalizedException(__('Refund was declined by the payment gateway: %1', $errorMessage));
		}
	}

	/**
	 * Build a PaymentToken source from raw token data returned by the TokenizeCard controller.
	 * The session was already exchanged for a PaymentToken via payments-vas/v1/tokens before
	 * this method is called — CommerceHub's refunds endpoint does not accept PaymentSession.
	 *
	 * Expected keys in $formData:
	 *   new_card_token_data, new_card_token_source, new_card_exp_month,
	 *   new_card_exp_year, new_card_name_on_card
	 *
	 * @param  array $formData
	 * @return PaymentToken
	 * @throws LocalizedException
	 */
	private function buildTokenDataSource(array $formData): PaymentToken
	{
		$tokenData = trim((string)($formData['new_card_token_data'] ?? ''));
		if ($tokenData === '') {
			throw new LocalizedException(__('Card tokenization data is missing. Please re-enter your card details.'));
		}

		$tokenSource = $formData['new_card_token_source'] ?? '';
		$expMonth    = $formData['new_card_exp_month']    ?? '';
		$expYear     = $formData['new_card_exp_year']     ?? '';
		$nameOnCard  = $formData['new_card_name_on_card'] ?? '';

		$card = new Card();
		if ($expMonth)   { $card->setExpirationMonth($expMonth); }
		if ($expYear)    { $card->setExpirationYear($expYear); }
		if ($nameOnCard) { $card->setNameOnCard($nameOnCard); }

		$paymentToken = new PaymentToken();
		$paymentToken->setSourceType(self::PAYMENT_TOKEN_SOURCE_TYPE);
		$paymentToken->setTokenData($tokenData);
		if ($tokenSource) { $paymentToken->setTokenSource($tokenSource); }
		$paymentToken->setDeclineDuplicates(false);
		$paymentToken->setCard($card);
		return $paymentToken;
	}

	/**
	 * Build a PaymentToken source from a stored vault token.
	 *
	 * @param array $formData
	 * @param int $customerId
	 * @return PaymentToken
	 * @throws LocalizedException
	 */
	private function buildVaultSource(array $formData, int $customerId): PaymentToken
	{
		$publicHash = $formData['vault_token_hash'] ?? '';
		if (empty($publicHash)) {
			throw new LocalizedException(__('Please select a saved card.'));
		}

		$tokens = $this->paymentTokenRepository->getList(
			$this->searchCriteriaBuilder
				->addFilter('public_hash', $publicHash)
				->addFilter('customer_id', $customerId)
				->addFilter('is_active', 1)
				->create()
		)->getItems();

		if (empty($tokens)) {
			throw new LocalizedException(__('The selected payment token could not be found or is no longer active.'));
		}

		$vaultToken   = reset($tokens);
		$tokenData    = preg_replace(self::TS_SUFFIX_PATTERN, '', $vaultToken->getGatewayToken());
		$details      = json_decode($vaultToken->getTokenDetails() ?: '{}', true);
		$tokenSource  = $details['tokenSource'] ?? '';
		$expDateParts = isset($details['expirationDate']) ? explode('/', $details['expirationDate']) : [];
		$expMonth     = $expDateParts[0] ?? ($details['expMonth'] ?? '');
		$expYear      = $expDateParts[1] ?? ($details['expYear'] ?? '');
		$nameOnCard   = $details['nameOnCard'] ?? '';

		$card = new Card();
		if ($expMonth) { $card->setExpirationMonth($expMonth); }
		if ($expYear)  { $card->setExpirationYear($expYear); }
		if ($nameOnCard) { $card->setNameOnCard($nameOnCard); }

		$paymentToken = new PaymentToken();
		$paymentToken->setSourceType(self::PAYMENT_TOKEN_SOURCE_TYPE);
		$paymentToken->setTokenData($tokenData);
		if ($tokenSource) { $paymentToken->setTokenSource($tokenSource); }
		$paymentToken->setDeclineDuplicates(false);
		$paymentToken->setCard($card);
		return $paymentToken;
	}


        /**
         * Build the RefundRequest model — without referenceTransactionDetails.
         *
         * @param OpenRefund $openRefund
         * @param PaymentToken $source
         * @return RefundRequest
         */
        private function buildRefundRequest(OpenRefund $openRefund, $source): RefundRequest
	{
		$amount = new Amount();
		$amount->setTotal((float)$openRefund->getAmount());
		$amount->setCurrency('USD');
		$this->logger->logDebug(3, "Open Refund Amount Data Builder:\n" . $amount->__toString());

		$this->logger->logDebug(3, "Open Refund Payment Source Data Builder:\n" . json_encode(json_decode(json_encode($source), true), JSON_PRETTY_PRINT));

		$merchantDetails = new MerchantDetails();
		$merchantDetails->setMerchantId($this->config->getMerchantId());
		$merchantDetails->setTerminalId($this->config->getTerminalId());
		$merchantDetails->setMerchantPartner(MerchantPartnerHelper::createMerchantPartner($this->config));
		$this->logger->logDebug(3, "Open Refund Merchant Details Data Builder:\n" . $merchantDetails->__toString());

		$transactionDetails = new TransactionDetails();
		$transactionDetails->setCaptureFlag($this->config->isOpenRefundCaptureFlag());
		$transactionDetails->setCreateToken(false);
		$transactionDetails->setAccountVerification(false);
		$transactionDetails->setMerchantTransactionId(substr(uniqid('or_', true), 0, 36));

		$this->logger->logDebug(3, "Open Refund Transaction Details Data Builder:\n" . $transactionDetails->__toString());

		$customer   = null;
		$customerId = $openRefund->getCustomerId();
		if ($customerId) {
			$customer = new Customer();
			$customer->setMerchantCustomerId((string)$customerId);

			$fullName = trim((string)($openRefund->getCustomerName() ?? ''));
			if ($fullName !== '') {
				$parts = explode(' ', $fullName, 2);
				$customer->setFirstName($parts[0]);
				if (isset($parts[1])) { $customer->setLastName($parts[1]); }
			}

			$email = trim((string)($openRefund->getCustomerEmail() ?? ''));
			if ($email !== '') { $customer->setEmail($email); }

			$this->logger->logDebug(3, "Open Refund Customer Data Builder:\n" . $customer->__toString());
		}

		$refundRequest = new RefundRequest();
		$refundRequest->setSource($source);
		$refundRequest->setAmount($amount);
		$refundRequest->setTransactionDetails($transactionDetails);
		$refundRequest->setMerchantDetails($merchantDetails);
		if ($customer !== null) { $refundRequest->setCustomer($customer); }
		return $refundRequest;
	}
}