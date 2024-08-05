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
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Webapi\Exception;
use Psr\Log\LoggerInterface;

/**
 * Class TokenizeSession
 */
class TokenizeSession extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
	const SESSION_ID_KEY = "session_id";
	const WEBSITE_ID_KEY = "website_id";
	const CUSTOMER_ID_KEY = "customer_id";
	const CARD_ALREADY_ADDED_IN_VAULT = 'Payment card already exists in vault';
	
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
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	* @var PaymentTokenManagementInterface
	*/
	private $paymentTokenManager;

	/**
	 * @var EncryptorInterface
	 */
	private $encryptor;

	/**
	* @param Context $context
	* @param LoggerInterface $logger
	* @param TokenizationRequest $chAdapter
	*/
	public function __construct(
		Context $context,
		TokenizationRequest $chAdapter,
		VaultDetailsHandler $vaultHandler,
		PaymentTokenRepositoryInterface $paymentTokenRepository,
		LoggerInterface $logger,
		PaymentTokenManagementInterface $paymentTokenManager,
		EncryptorInterface $encryptor,
		\Magento\Framework\Message\ManagerInterface $messageManager,
		VaultPaymentTokenUtils $vaultPaymentTokenUtils,
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

		if($this->vaultPaymentTokenUtils->doesTokenExist(
			$tokenResponse["paymentTokens"][0]["tokenData"], 
			ConfigProvider::CODE, 
			$customerId, 
			$tokenResponse["source"]["card"]["expirationMonth"],
			$tokenResponse["source"]["card"]["expirationYear"]
		))
		{
			$this->messageManager->addNotice(__(self::CARD_ALREADY_ADDED_IN_VAULT));
			throw new \InvalidArgumentException(self::CARD_ALREADY_ADDED_IN_VAULT);
		}

		$paymentToken = $this->vaultHandler->getVaultCardToken($tokenResponse);
		$paymentToken->setCustomerId($customerId);
		$paymentToken->setPaymentMethodCode(ConfigProvider::CODE);
		$paymentToken->setIsActive(true);
		$paymentToken->setIsVisible(true);
		$paymentToken->setWebsiteId($websiteId);
		$paymentToken->setPublicHash($this->generatePublicHash($paymentToken));
		
		$tokenDuplicate = $this->paymentTokenManager->getByPublicHash(
					$paymentToken->getPublicHash(),
					$paymentToken->getCustomerId()
			);

			if (!empty($tokenDuplicate)) {
					if ($paymentToken->getIsVisible() || $tokenDuplicate->getIsVisible()) {
						$paymentToken->setEntityId($tokenDuplicate->getEntityId());
						$paymentToken->setIsVisible(true);
					} elseif ($paymentToken->getIsVisible() === $tokenDuplicate->getIsVisible()) {
						$paymentToken->setEntityId($tokenDuplicate->getEntityId());
					} else {
						$paymentToken->setPublicHash(
								$this->encryptor->getHash(
									$paymentToken->getPublicHash() . $paymentToken->getGatewayToken()
								)
						);
				}
		}
		$this->paymentTokenRepository->save($paymentToken);

		$response->setHttpResponseCode(201);
		$response->setData($paymentToken->getTokenDetails());

	} catch (\InvalidArgumentException $e) {
		$this->logger->critical($e);
		return $this->processCardTokenizationError($response);
	} catch (\Exception $e) {
		$this->logger->critical($e);
		return $this->processBadRequest($response);
	}
		return $response;
	}

	 /**
	 * Generate vault payment public hash
	 *
	 * @param PaymentTokenInterface $paymentToken
	 * @return string
	 */
	protected function generatePublicHash(\Magento\Vault\Model\PaymentToken $paymentToken)
	{
		$hashKey = $paymentToken->getGatewayToken();
		if ($paymentToken->getCustomerId()) {
			$hashKey = $paymentToken->getCustomerId();
		}

		$hashKey .= $paymentToken->getPaymentMethodCode()
			. $paymentToken->getType()
			. $paymentToken->getTokenDetails();

		return $this->encryptor->getHash($hashKey);
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
	
	private function processCardTokenizationError(ResultInterface $response)
	{
		$response->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
		$response->setData(['message' => __(self::CARD_ALREADY_ADDED_IN_VAULT)]);

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
