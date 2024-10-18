<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Controller\Valuelink;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Webapi\Exception;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Checkout\Model\Cart as CustomerCart;
use Fiserv\Payments\Model\Valuelink\ValuelinkQuoteRecord;
use Fiserv\Payments\Model\Service\Valuelink\ValuelinkQuoteManager;

/**
 * Class RemoveValuelinkCard
 */
class RemoveValuelinkCard extends \Magento\Checkout\Controller\Cart implements HttpPostActionInterface
{
	const HTTP_UNAUTHORIZED = 401;
	const KEY_STORE_ID = "store_id";

    /**
     * @var MultiLevelLogger
     */
    private $logger;

	private $quoteManager;

    /**
    * @param Context $context
    * @param MultiLevelLogger $logger
    * @param CredentialsRequest $chAdapter
    */
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
		CustomerCart $cart,
		MultiLevelLogger $logger,
		ValuelinkQuoteManager $quoteManager
	) {
		parent::__construct(
			$context,
			$scopeConfig,
			$checkoutSession,
			$storeManager,
			$formKeyValidator,
			$cart
		);	

		$this->quoteManager = $quoteManager;
		$this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
		$this->logger->logInfo(1, "Attempting to remove gift card from cart...");
		
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

		try {
			$data = $this->getRequest()->getPostValue();
			if(!isset($data[ValuelinkQuoteRecord::SESSION_ID_KEY]))
			{
				throw new InvalidArgumentException("Required Session ID parameter not found.");
			}			
			$data[ValuelinkQuoteRecord::BALANCE_KEY] = 0;
			$data[ValuelinkQuoteRecord::TIMESTAMP_KEY] = time();

			$recordsToRemove = array();
			array_push($recordsToRemove, ValuelinkQuoteRecord::createFromArray($data));

			$this->quoteManager->RemoveValuelinkCardsFromQuote($recordsToRemove, $this->cart->getQuote());

			$this->logger->logInfo(1, "Gift card has been removed from cart");
			$this->messageManager->addSuccess(__('Gift card has been removed from cart.'));
			$response->setData(['valuelink' => "Gift card has been removed from cart."]);
		} catch(\Exception $e) {
			$this->logger->logError(1, "Error occurred while removing a ValueLink card");
			$this->logger->logCritical(2, $e);
			return $this->processBadRequest($response, $e);
		}
 		
		return $response;
	}

	/**
	* Return response for bad request
	* @param ResultInterface $response
	* @return ResultInterface
	*/
	private function processBadRequest(ResultInterface $response, \Exception $e)
	{
		$response->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
		$response->setData(['message' => __(print_r($response->getData(), true))]);
		$this->messageManager->addErrorMessage(__($e->getMessage()));

		return $response;
	}
}
