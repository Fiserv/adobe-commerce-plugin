<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Controller\Vault;

use Fiserv\Payments\Model\Adapter\CommerceHub\TokenizationRequest;
use Fiserv\Payments\Model\System\Utils\VaultPaymentTokenUtils;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Fiserv\Payments\Gateway\Response\CommerceHub\VaultDetailsHandler;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Framework\Webapi\Exception;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Class TokenizeSession
 */
class TokenizeSession extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
	const SESSION_ID_KEY = "session_id";
	const WEBSITE_ID_KEY = "website_id";
	const CUSTOMER_ID_KEY = "customer_id";
	const CARD_ALREADY_ADDED_IN_VAULT = 'Payment card already exists in vault';
	const PIN_ONLY = "pin_only";
	const PIN_ONLY_CARD_MESSAGE = "PIN-only debit cards are only allowed for card present purchases";
	
	protected $vaultPaymentTokenUtils;
	
	protected $messageManager;

	/**
	 * @var TokenizationRequest
	 */
	private $chAdapter;

	/**
	 * @var VaultDetailsHandler
	 */
	protected $vaultHandler;

	/**
	 * @var PaymentTokenRepositoryInterface
	 */
	protected $paymentTokenRepository;

	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	* @var PaymentTokenManagementInterface
	*/
	private $paymentTokenManager;

	private $encryptor;
	
	/**
	* @param Context $context
	* @param MultiLevelLogger $logger
	* @param TokenizationRequest $chAdapter
	*/
	public function __construct(
		Context $context,
		TokenizationRequest $chAdapter,
		VaultDetailsHandler $vaultHandler,
		PaymentTokenRepositoryInterface $paymentTokenRepository,
		MultiLevelLogger $logger,
		PaymentTokenManagementInterface $paymentTokenManager,
		\Magento\Framework\Message\ManagerInterface $messageManager,
		VaultPaymentTokenUtils $vaultPaymentTokenUtils,
		EncryptorInterface $encryptor
	) {
		parent::__construct($context);
		$this->vaultPaymentTokenUtils = $vaultPaymentTokenUtils;
		$this->messageManager = $messageManager;
		$this->chAdapter = $chAdapter;
		$this->vaultHandler = $vaultHandler;
		$this->paymentTokenRepository = $paymentTokenRepository;
		$this->logger = $logger;
		$this->paymentTokenManager = $paymentTokenManager;
		$this->encryptor = $encryptor;
	}

	/**
	 * @inheritdoc
	 */
	public function execute()
	{
		$response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

	try {
		$sessionId = $this->getRequest()->getParam(self::SESSION_ID_KEY);
		$customerId = $this->getRequest()->getParam(self::CUSTOMER_ID_KEY);
		$websiteId = $this->getRequest()->getParam(self::WEBSITE_ID_KEY);
		
		if ($sessionId == null || $customerId == null) {
			return $this->processBadRequest($response); 
		}

		$tokenResponse = $this->chAdapter->tokenizeSession($sessionId);

		// need to check if cardDetails exists because this is returned only when
		// card metadata entitlement is active in Marketplace
		if(isset($tokenResponse["cardDetails"]) && isset($tokenResponse["cardDetails"][0]) && self::PIN_ONLY === strtolower($tokenResponse["cardDetails"][0]["detailedCardProduct"]) ) {
			$this->logger->logInfo(1, "PIN-only card, not for online shopping");
			$this->messageManager->addNotice(__(self::PIN_ONLY_CARD_MESSAGE));
			throw new \InvalidArgumentException(self::PIN_ONLY_CARD_MESSAGE);
		}

		$existingToken = $this->vaultPaymentTokenUtils->doesTokenExist(
			$tokenResponse["paymentTokens"][0]["tokenData"], 
			ConfigProvider::CODE, 
			$customerId, 
			$tokenResponse["source"]["card"]["expirationMonth"],
			$tokenResponse["source"]["card"]["expirationYear"]
		); 

		if($existingToken !== false && isset($existingToken["is_visible"]) && $existingToken["is_visible"])
		{
			$this->logger->logInfo(1, "Card not tokenized. Already stored");
			$this->messageManager->addNotice(__(self::CARD_ALREADY_ADDED_IN_VAULT));
			throw new \InvalidArgumentException(self::CARD_ALREADY_ADDED_IN_VAULT);
		}

		$paymentToken = $this->vaultHandler->getVaultCardToken($tokenResponse, true);
		$paymentToken->setCustomerId($customerId);
		$paymentToken->setWebsiteId($websiteId);
		
		if ($existingToken !== false)
		{
			$tokenDuplicate = $this->paymentTokenRepository->getById($existingToken["entity_id"]);
			if (!empty($tokenDuplicate)) {
				$tokenDuplicate->setIsActive(false);
				$paymentToken->setPublicHash($tokenDuplicate->getPublicHash());
				$tokenDuplicate->setPublicHash($this->encryptor->getHash($tokenDuplicate->getPublicHash() . $tokenDuplicate->getGatewayToken()));
				$this->paymentTokenRepository->save($tokenDuplicate);
			}
		}

		$this->paymentTokenRepository->save($paymentToken);

		$response->setHttpResponseCode(201);
		$response->setData($paymentToken->getTokenDetails());

	} catch (\InvalidArgumentException $e) {
		$this->logger->logWarning(2, $e);
		return $this->processCardTokenizationError($response, $e->getMessage());
	} catch (\Exception $e) {
		$this->logger->logCritical(1, "Unknown error occurred in tokenization process");
		$this->logger->logCritical(2, $e);
		return $this->processBadRequest($response);
	}
		return $response;
	}

	/**
	 * Return response for bad request
	 * @param ResultInterface $response
	 * @return ResultInterface
	 */
	private function processBadRequest(ResultInterface $response)
	{
		$response->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
		$response->setData(['message' => __('Sorry, but something went wrong')]);

		return $response;
	}
	
	private function processCardTokenizationError(ResultInterface $response, string $message)
	{
		$response->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
		$response->setData(['message' => __($message)]);

		return $response;
	}

	public function createCsrfValidationException(RequestInterface $request): ? InvalidRequestException
	{
		return null;
	}
		
	public function validateForCsrf(RequestInterface $request): ?bool
	{
		return true;
	}
}
