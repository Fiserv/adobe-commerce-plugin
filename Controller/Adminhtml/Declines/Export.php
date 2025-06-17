<?php

namespace Fiserv\Payments\Controller\Adminhtml\Declines;

use Magento\Backend\App\Action;
use Magento\Framework\App\Response\FileFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\PageFactory;
use Fiserv\Payments\Model\Export\Declines\Orders;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class Export
 */
class Export extends Action implements HttpGetActionInterface
{
	protected $fileFactory;
	protected $ordersExport;
	protected $logger;
	
	/**
	 * @param Context $context
	 * @param FileFactory $fileFactory
	 * @param Orders $ordersExport
	 * @param MultiLevelLogger $logger
	 */
	public function __construct(
		Context $context,
		FileFactory $fileFactory,
		Orders $ordersExport,
		MultiLevelLogger $logger
	) {
		parent::__construct($context);
		$this->logger = $logger;
		$this->fileFactory = $fileFactory;
		$this->ordersExport = $ordersExport;
	}

	/**
	 * @inheritdoc
	 */
	public function execute()
	{
		try {
			$format = $this->getRequest()->getParam('format');

			$fileName = "failed_orders";
			$filePath = '';
			$mimeTYpe = '';

			if ($format == 'csv')
			{
				$fileName = $fileName . ".csv";
				$filePath = $this->ordersExport->getCsvFile();
				$mimeType = 'text/csv';
			}
			else if ($format == 'xlsx')
			{
				$fileName = $fileName . ".xlsx";
				$filePath = $this->ordersExport->getXlsxFile();
			}
			else 
			{
				throw new \Exception('Export format not found.');
			}

			return $this->fileFactory->create(
				$fileName,
				$filePath,
				\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR
			);
		
		} catch (\Exception $e) {
			$this->logger->logCritical(1, "An error occurred while exporting failed orders.");
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
