<?php

namespace Fiserv\Payments\Controller\Adminhtml\Declines;

use Magento\Backend\App\Action;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultFactory;
use Fiserv\Payments\Model\Export\Declines\Orders;
use Fiserv\Payments\Model\Export\Declines\Transactions; // Added
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem;

class Export extends Action implements HttpGetActionInterface
{
	protected $fileFactory;
	protected $ordersExport;
	protected $transactionsExport; // Added
	protected $logger;
	protected $resultJsonFactory;
	protected $filesystem;

	public function __construct(
		Context $context,
		FileFactory $fileFactory,
		Orders $ordersExport,
		Transactions $transactionsExport, // Added
		MultiLevelLogger $logger,
		JsonFactory $resultJsonFactory,
		Filesystem $filesystem
	) {
		parent::__construct($context);
		$this->fileFactory = $fileFactory;
		$this->ordersExport = $ordersExport;
		$this->transactionsExport = $transactionsExport; // Added
		$this->logger = $logger;
		$this->resultJsonFactory = $resultJsonFactory;
		$this->filesystem = $filesystem;
	}

	public function execute()
	{
		try {
			$format = $this->getRequest()->getParam('format');
			$type = $this->getRequest()->getParam('type', 'orders');

			if (!$format) {
				throw new \Exception('Export format not specified.');
			}

			$filters = $this->getFiltersByType($type);

			if ($type === 'transactions') {
				if (empty($filters['orderIncrementId'])) {
					throw new \Exception('Order Increment ID is missing for transaction lookup.');
				}
			}

			$fileType = $type === 'transactions' ? 'failed_transactions' : 'failed_orders';
			$fileName = "{$fileType}.{$format}";

			$filePath = $this->getExportFile($fileName, $format, $type, $filters);

			$absoluteFilePath = $this->filesystem
				->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR)
				->getAbsolutePath($filePath);

			if (!file_exists($absoluteFilePath)) {
				throw new NotFoundException(__('Exported file not found.'));
			}

			return $this->fileFactory->create(
				$fileName,
				[
					'type' => 'filename',
					'value' => $filePath,
					'rm' => false
				],
				\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR,
				$this->getMimeType($format)
			);
		} catch (\Exception $e) {
			$this->logger->logCritical(1, "An error occurred while exporting data.");
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
		$resultForward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
		return $resultForward->forward('noroute');
	}

	private function getFiltersByType($type)
	{
		if ($type === 'transactions') {
			return [
				'searchFilter' => $this->getRequest()->getParam('searchFilter', ''),
				'approvalStatus' => $this->getRequest()->getParam('approvalStatus', ''),
				'transactionState' => $this->getRequest()->getParam('transactionState', ''),
				'fromDate' => $this->getRequest()->getParam('fromDate'),
				'toDate' => $this->getRequest()->getParam('toDate'),
				'orderIncrementId' => $this->getRequest()->getParam('orderIncrementId')
			];
		} else {
			return [
				'searchFilter' => $this->getRequest()->getParam('searchFilter', ''),
				'approvalStatus' => $this->getRequest()->getParam('approvalStatus', ''),
				'orderState' => $this->getRequest()->getParam('orderState', ''),
				'transactionState' => $this->getRequest()->getParam('transactionState', ''),
				'fromDate' => $this->getRequest()->getParam('fromDate'),
				'toDate' => $this->getRequest()->getParam('toDate')
			];
		}
	}

	private function getExportFile($fileName, $format, $type, array $filters)
	{
		switch ($format) {
			case 'csv':
				return $type === 'transactions'
					? $this->transactionsExport->getCsvFile($fileName, $filters, $type)
					: $this->ordersExport->getCsvFile($fileName, $filters, $type);
			case 'xls':
				return $type === 'transactions'
					? $this->transactionsExport->getExcelFile($fileName, $filters, $type)
					: $this->ordersExport->getExcelFile($fileName, $filters, $type);
			default:
				throw new \Exception('Unsupported export format.');
		}
	}

	private function getMimeType($format)
	{
		switch ($format) {
			case 'csv':
				return 'text/csv';
			case 'xls':
				return 'application/vnd.ms-excel';
			default:
				throw new \Exception('Unsupported export format.');
		}
	}
}
