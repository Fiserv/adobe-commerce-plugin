<?php
namespace Fiserv\Payments\Model\Service;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Fiserv\Payments\Helper\MerchantPartnerHelper;
use Fiserv\Payments\Lib\CommerceHub\Model\Amount;
use Fiserv\Payments\Lib\CommerceHub\Model\Card;
use Fiserv\Payments\Lib\CommerceHub\Model\Customer;
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
use Magento\Sales\Api\OrderRepositoryInterface;
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
		private readonly MultiLevelLogger $logger,
		private readonly OrderRepositoryInterface $orderRepository
	) {}

	/**Submit an open refund: validate → resolve source → build request → save → call CH → persist result.*/
	public function submit(OpenRefund $openRefund, array $formData): void
	{
		$orderId = trim((string)($openRefund->getOrderIncrementId() ?? ''));
		$sourceType = $formData['payment_source_type'] ?? 'vault';

		$this->logger->logInfo(1, 'Initiating open refund', "Order ID: $orderId");
		$this->logger->logInfo(2, 'Open Refund Amount: ' . $openRefund->getAmount() . ' USD', "Order ID: $orderId");
		$this->logger->logInfo(2, 'Open Refund Customer ID: ' . ($openRefund->getCustomerId() ?? 'n/a'), "Order ID: $orderId");
		$this->logger->logInfo(2, 'Open Refund Payment source type: ' . $sourceType, "Order ID: $orderId");

		$limit = (float)($this->config->getValue('open_refund_transaction_limit') ?? 0);
		if ($limit > 0 && (float)$openRefund->getAmount() > $limit) {
			throw new LocalizedException(__('Refund amount exceeds the configured transaction limit of %1.', $limit));
		}

		$source = $sourceType === 'new_card'
			? $this->buildSessionSource($formData)
			: $this->buildVaultSource($formData, (int)$openRefund->getCustomerId());

		$refundRequest = $this->buildRefundRequest($openRefund, $source, $orderId);

		$openRefund->setStatus(OpenRefund::STATUS_PENDING);
		$this->openRefundRepository->save($openRefund);

		$this->logger->logInfo(1, 'Open Refund Sending open refund request to CommerceHub', "Order ID: $orderId");
		$this->logger->logDebug(3, 'Open Refund Request payload: ' . json_encode(json_decode(json_encode($refundRequest), true), JSON_PRETTY_PRINT), "Order ID: $orderId");

		try {
			$chResponse = $this->httpAdapter->sendRequest($refundRequest, self::REFUNDS_ENDPOINT);
		} catch (\Exception $e) {
			$this->logger->logCritical(1, 'Open Refund HTTP request to CommerceHub failed', "Order ID: $orderId");
			$this->logger->logCritical(2, $e->getMessage(), "Order ID: $orderId");
			$openRefund->setStatus(OpenRefund::STATUS_FAILED);
			$this->openRefundRepository->save($openRefund);
			throw new LocalizedException(__('Communication with the payment gateway failed. Please try again.'));
		}

		$statusCode   = $chResponse->getStatusCode();
		$responseBody = json_decode($chResponse->getBody(), true) ?? [];
		$transactionState = $responseBody['gatewayResponse']['transactionState'] ?? '';

		$this->logger->logDebug(3, 'Open Refund Response (HTTP ' . $statusCode . '): ' . json_encode($responseBody, JSON_PRETTY_PRINT), "Order ID: $orderId");

		if ($statusCode >= 200 && $statusCode < 300 && strtoupper($transactionState) === 'CAPTURED') {
			$transactionId = $responseBody['gatewayResponse']['transactionProcessingDetails']['transactionId'] ?? '';
			$maskedCard    = $responseBody['source']['card']['last4'] ?? '';

			$openRefund->setStatus(OpenRefund::STATUS_SUCCESS);
			$openRefund->setTransactionId($transactionId);
			$openRefund->setMaskedCard($maskedCard);
			$this->openRefundRepository->save($openRefund);
			$this->addOrderHistoryComment($openRefund, $transactionId, $orderId);

			$this->logger->logInfo(2, 'Open Refund Transaction ID: ' . $transactionId, "Order ID: $orderId");
		} else {
			$errorMessage = $responseBody['error'][0]['message']
				?? $responseBody['errors'][0]['message']
				?? $transactionState
				?? 'Unknown error';

			$openRefund->setStatus(OpenRefund::STATUS_FAILED);
			$this->openRefundRepository->save($openRefund);

			$this->logger->logError(1, 'Open Refund CH returned non-CAPTURED state: ' . $transactionState . ' (HTTP ' . $statusCode . ')', "Order ID: $orderId");
			$this->logger->logError(2, 'Open Refund Error message: ' . $errorMessage, "Order ID: $orderId");

			throw new LocalizedException(__('Refund was declined by the payment gateway: %1', $errorMessage));
		}
	}

	/**
	 * Build a PaymentSession source from a hosted-fields session token.
	 *
	 * @param array $formData
	 * @return PaymentSession
	 * @throws LocalizedException
	 */
	private function buildSessionSource(array $formData): PaymentSession
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
	 * Write an order history comment for the open refund.
	 *
	 * @param OpenRefund $openRefund
	 * @param string $transactionId
	 * @param string $orderId
	 */
	private function addOrderHistoryComment(OpenRefund $openRefund, string $transactionId, string $orderId): void
	{
		if ($orderId === '') {
			$this->logger->logInfo(2, 'Open Refund No order_increment_id set — skipping order history comment', "Order ID: $orderId");
			return;
		}

		try {
			$items = $this->orderRepository->getList(
				$this->searchCriteriaBuilder->addFilter('increment_id', $orderId, 'eq')->create()
			)->getItems();

			if (empty($items)) {
				$this->logger->logError(2, 'Open Refund Could not find order to add history comment', "Order ID: $orderId");
				return;
			}

			$maskedCard = $openRefund->getMaskedCard();
			$cardSuffix = $maskedCard ? ' (****' . $maskedCard . ')' : '';

			$order = reset($items);
			$order->addCommentToStatusHistory(sprintf(
				'Open refund of $%s USD captured%s. Transaction ID: "%s"',
				number_format((float)$openRefund->getAmount(), 2),
				$cardSuffix,
				$transactionId
			));
			$this->orderRepository->save($order);
		} catch (\Throwable $e) {
			$this->logger->logError(2, 'Open Refund Failed to add order history comment: ' . $e->getMessage(), "Order ID: $orderId");
		}
	}

	/**
	 * Build the RefundRequest model — without referenceTransactionDetails.
	 *
	 * @param OpenRefund $openRefund
	 * @param PaymentToken|PaymentSession $source
	 * @param string $orderId
	 * @return RefundRequest
	 */
	private function buildRefundRequest(OpenRefund $openRefund, $source, string $orderId): RefundRequest
	{
		$amount = new Amount();
		$amount->setTotal((float)$openRefund->getAmount());
		$amount->setCurrency('USD');
		$this->logger->logDebug(3, "Open Refund Amount Data Builder:\n" . $amount->__toString(), "Order ID: $orderId");

		$this->logger->logDebug(3, "Open Refund Payment Source Data Builder:\n" . json_encode(json_decode(json_encode($source), true), JSON_PRETTY_PRINT), "Order ID: $orderId");

		$merchantDetails = new MerchantDetails();
		$merchantDetails->setMerchantId($this->config->getMerchantId());
		$merchantDetails->setTerminalId($this->config->getTerminalId());
		$merchantDetails->setMerchantPartner(MerchantPartnerHelper::createMerchantPartner($this->config));
		$this->logger->logDebug(3, "Open Refund Merchant Details Data Builder:\n" . $merchantDetails->__toString(), "Order ID: $orderId");

		$transactionDetails = new TransactionDetails();
		$transactionDetails->setCaptureFlag($this->config->isOpenRefundCaptureFlag());
		$transactionDetails->setCreateToken(false);
		$transactionDetails->setAccountVerification(false);
		$transactionDetails->setMerchantTransactionId(substr(uniqid('or_', true), 0, 36));

		$referenceTransactionId = trim((string)($openRefund->getReferenceTransactionId() ?? ''));
		if ($referenceTransactionId !== '') { $transactionDetails->setMerchantOrderId($referenceTransactionId); }

		$this->logger->logDebug(3, "Open Refund Transaction Details Data Builder:\n" . $transactionDetails->__toString(), "Order ID: $orderId");

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

			$this->logger->logDebug(3, "Open Refund Customer Data Builder:\n" . $customer->__toString(), "Order ID: $orderId");
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