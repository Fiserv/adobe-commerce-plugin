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
use Magento\Checkout\Controller\Cart as CartAction;
use Fiserv\Payments\Model\Valuelink\ValuelinkQuoteRecord;
use Fiserv\Payments\Model\Service\Valuelink\ValuelinkQuoteManager;

/**
 * Class ApplyValuelinkCard
 */
class ApplyValuelinkCard extends CartAction implements HttpPostActionInterface
{
	const CART_ID_KEY = "cartId";
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
		\Magento\Checkout\Model\Cart $cart,
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
		$this->logger->logInfo(1, "Attempting to apply gift card to cart...");
		
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

		try {
			$data = $this->getRequest()->getPostValue();
			if(!isset($data[ValuelinkQuoteRecord::SESSION_ID_KEY]))
			{
					throw new InvalidArgumentException("Required Session ID parameter not found.");
			}			

			if(!isset($data[ValuelinkQuoteRecord::BALANCE_KEY]))
			{
					throw new InvalidArgumentException("Required balance parameter not found.");
			}

			if(!isset($data[self::CART_ID_KEY]))
			{
					throw new InvalidArgumentException("Required cart ID parameter not found.");
			}

			$data[ValuelinkQuoteRecord::TIMESTAMP_KEY] = time();
			$quote = $this->cart->getQuote();

			$quoteRecord = ValuelinkQuoteRecord::createFromArray($data);

			$this->quoteManager->AddValuelinkCardToQuote($quoteRecord, $quote);
			$this->logger->logInfo(1, "Gift card has been applied to cart");
			$this->messageManager->addSuccess(__("Gift card has been applied to cart."));
			
			$response->setData(['valuelink' => "Gift card has been applied to cart."]);
		} catch(\Exception $e) {
			$this->logger->logError(1, "Gift card failed to be applied");
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
		$response->setData(['message' => __($e->getMessage())]);
		$this->messageManager->addErrorMessage(__($e->getMessage()));
		
		return $response;
    }
}
