<?php 

namespace Fiserv\Payments\Controller\Adminhtml\Declines;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Webapi\Exception;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * Class Transaction
 */
class Preview extends Action implements HttpGetActionInterface
{
	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	private $pageFactory;

	private $forwardFactory;

	/**
	 * @param Context $context
	 * @param MultiLevelLogger $logger
	 * @param GetPaymentTokenCommand $command
	 */
	public function __construct(
		Context $context,
		MultiLevelLogger $logger,
		PageFactory $pageFactory,
		ForwardFactory $forwardFactory,
	) {
		parent::__construct($context);
		$this->logger = $logger;
		$this->pageFactory = $pageFactory;
		$this->forwardFactory = $forwardFactory;
	}

	/**
	 * @inheritdoc
	 */
	public function execute()
	{
		$resultPage = $this->pageFactory->create();

		try {
			$resultPage->setActiveMenu("Fiserv_Payments::failed_transaction_preview");
			$resultPage->getConfig()->getTitle()->prepend(__('UNSUCESSFUL ORDERS'));

			return $resultPage;
		} catch (\Exception $e) {
			 $this->logger->logCritical(1, "An error occurred in the retrieval of declined transaction");
			 $this->logger->logCritical(2, $e->getMessage());
			 return $this->processBadRequest();
		 }
	 }
}
			
