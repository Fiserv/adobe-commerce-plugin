<?php 
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Controller\Vault;

use Fiserv\Payments\Gateway\Command\CommerceHub\GetPaymentTokenCommand;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use \Magento\Customer\Model\Session;
use Magento\Framework\Webapi\Exception;
use Psr\Log\LoggerInterface;
use Fiserv\Payments\Model\System\Utils\PaymentTokenUtil;

/**
 * Class GetCommerceHubToken
 */
class GetCommerceHubToken extends Action implements HttpGetActionInterface
{
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var Session
	 */
	private $session;

	/**
	 * @var GetPaymentTokenCommand
	 */
	private $command;

	/**
	 * @param Context $context
	 * @param LoggerInterface $logger
	 * @param Session $session
	 * @param GetPaymentTokenCommand $command
	 */
	public function __construct(
		Context $context,
		LoggerInterface $logger,
		Session $session,
		GetPaymentTokenCommand $command
	) {
		parent::__construct($context);
		$this->logger = $logger;
		$this->session = $session;
		$this->command = $command;
	}

	/**
	 * @inheritdoc
	 */
	public function execute()
	{
		$response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

		try {
			$publicHash = $this->getRequest()->getParam('public_hash');
			$customerId = $this->getRequest()->getParam('customer_id');
			if (!isset($customerId) || empty($customerId)) {
				$customerId = $this->session->getCustomer()->getId();
			}
			$result = $this->command->execute(
				['public_hash' => $publicHash, 'customer_id' => $customerId]
			)
				->get();
			$response->setData(['paymentToken' => PaymentTokenUtil::getTokenDataFromPersistenceFormat($result['paymentToken'])]);
		} catch (\Exception $e) {
			$this->logger->critical($e);
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
}