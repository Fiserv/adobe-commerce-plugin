<?php 

namespace Fiserv\Payments\Controller\Adminhtml\Declines;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Webapi\Exception;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Fiserv\Payments\Block\CommerceHub\AdminHtml\Declines\Transaction as TransactionBlock;
use Fiserv\Payments\Model\ResourceModel\FailedTransaction;

/**
 * Class Transaction
 */
class Transaction extends Action implements HttpGetActionInterface
{
	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

	/**
	 * @var Session
	 */
	private $session;

	private $pageFactory;

	private $forwardFactory;

	private $resourceModel;

	/**
	 * @param Context $context
	 * @param MultiLevelLogger $logger
	 * @param Session $session
	 * @param GetPaymentTokenCommand $command
	 */
	public function __construct(
		Context $context,
		MultiLevelLogger $logger,
		PageFactory $pageFactory,
		ForwardFactory $forwardFactory,
		FailedTransaction $resourceModel,
		Session $session
	) {
		parent::__construct($context);
		$this->logger = $logger;
		$this->pageFactory = $pageFactory;
		$this->forwardFactory = $forwardFactory;
		$this->resourceModel = $resourceModel;
		$this->session = $session;
	}

	/**
	 * @inheritdoc
	 */
	public function execute()
	{
		$resultPage = $this->pageFactory->create();

		try {
			$transactionId = $this->getRequest()->getParam('transaction_id');
			if (!isset($transactionId) || empty($transactionId)) {
				throw new \Exception("Transaction ID not found");	
			}

			$txn = $this->resourceModel->getByTxnId($transactionId);
			if (!isset($txn) || empty($txn)) {
				throw new \Exception("Transaction (" . $transactionId . ") not found.");
			}

			$resultPage->getLayout()
				->getBlock('failed_transaction')
				->setData(TransactionBlock::KEY_TRANSACTION, $txn);

			$resultPage->setActiveMenu("Fiserv_Payments::failed_transactions");
			$resultPage->getConfig()->getTitle()->prepend(__('Transaction ID ' . $transactionId));

			return $resultPage;
		} catch (\Exception $e) {
			$this->logger->logCritical(1, "An error occurred in the retrieval of declined transaction");
			$this->logger->logCritical(2, $e->getMessage());
			return $this->processBadRequest();
		}
	}

	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Fiserv_Payments::declines');
	}

	private function processBadRequest()
	{
		$resultForward = $this->forwardFactory->create();
		return $resultForward->forward('noroute');
	}
}
