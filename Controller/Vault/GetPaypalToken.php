<?php 
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Controller\Vault;

use Fiserv\Payments\Gateway\Command\PayPal\GetPaypalTokenCommand;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use \Magento\Customer\Model\Session;
use Magento\Framework\Webapi\Exception;
use Fiserv\Payments\Model\System\Utils\PayPal\PaypalTokenUtil;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class GetPaypalToken
 */
class GetPaypalToken extends Action implements HttpGetActionInterface
{
	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @var Session
	 */
	private $session;

	/**
	 * @var GetPaypalTokenCommand
	 */
	private $command;

	/**
	 * @param Context $context
	 * @param MultiLevelLogger $logger
	 * @param Session $session
	 * @param GetPaypalTokenCommand $command
	 */
	public function __construct(
		Context $context,
		MultiLevelLogger $logger,
		Session $session,
		GetPaypalTokenCommand $command
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
			$customerId = $this->getRequest()->getParam('customer_id');
			if (!isset($customerId) || empty($customerId)) {
				$customerId = $this->session->getCustomerId();
                throw new InvalidArgumentException(__('Customer ID is missing'));
            }
			// Ensure this is for PayPal token retrieval
			$result = $this->command->execute([
				'customer_id' => $customerId,
				'method_code' => 'paypal' // Explicitly set for PayPal
			]);
			if (!empty($result['success']) && !empty($result['paypal_token'])) {
				$response->setData(['success' => true, 'paypal_token' => $result['paypal_token']]);
			} else {
				throw new \RuntimeException(__('Failed to retrieve PayPal token'));
			}
		} catch (\Exception $e) {
			$this->logger->logCritical(1, "An error occurred in the retrieval of PayPal payment token data");
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
}
