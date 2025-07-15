<?php

namespace Fiserv\Payments\Model\Export\Declines;

use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Fiserv\Payments\Model\ResourceModel\FailedTransaction;
use Fiserv\Payments\Helper\FailedTransactionHelper;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class Transactions
{
	protected $csv;
	protected $filesystem;
	protected $failedTransactionHelper;
	protected $logger;
	protected $varDirectory;
	protected $failedTxn;
	protected $orderRepository;
	protected $searchCriteriaBuilder;

	public function __construct(
		Csv $csv,
		Filesystem $filesystem,
		FailedTransactionHelper $failedTransactionHelper,
		MultiLevelLogger $logger,
		FailedTransaction $failedTxn,
		OrderRepositoryInterface $orderRepository,
		SearchCriteriaBuilder $searchCriteriaBuilder
	) {
		$this->csv = $csv;
		$this->filesystem = $filesystem;
		$this->failedTransactionHelper = $failedTransactionHelper;
		$this->logger = $logger;
		$this->varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
		$this->failedTxn = $failedTxn;
		$this->orderRepository = $orderRepository;
		$this->searchCriteriaBuilder = $searchCriteriaBuilder;
	}

	public function getCsvFile($fileName, array $filters = [], $type = 'transactions')
	{
		$data = $this->getData($filters, $type);

		$rows = $this->prepareRows($data['transactions']);
		$filePath = 'export/' . $fileName;
		$absolutePath = $this->varDirectory->getAbsolutePath($filePath);

		// Create folder if it doesn't exist
		$directoryPath = $this->varDirectory->getAbsolutePath('export/');
		if (!$this->varDirectory->isDirectory($directoryPath)) {
			$this->varDirectory->create($directoryPath);
		}

		$this->csv->saveData($absolutePath, $rows);
		return $filePath;
	}

	public function getExcelFile($fileName, array $filters = [], $type = 'transactions')
	{
		$data = $this->getData($filters, $type);

		$rows = $this->prepareRows($data['transactions']);
		$filePath = 'export/' . $fileName;
		$absolutePath = $this->varDirectory->getAbsolutePath($filePath);

		// Create folder if it doesn't exist
		$directoryPath = $this->varDirectory->getAbsolutePath('export/');
		if (!$this->varDirectory->isDirectory($directoryPath)) {
			$this->varDirectory->create($directoryPath);
		}

		$file = fopen($absolutePath, 'w');
		foreach ($rows as $row) {
			fwrite($file, implode("\t", $row) . "\n");
		}
		fclose($file);

		return $filePath;
	}

	private function getData(array $filters, $type)
	{
		try {

			if (empty($filters['orderIncrementId'])) {
				throw new \Exception('Order Increment ID is missing for transaction lookup.');
			}

			return $this->failedTransactionHelper->getFailedTransactionsByOrderIncrementId(
				$filters['orderIncrementId'],
				$filters['page'] ?? 1,
				$filters['pageSize'] ?? 20,
				$filters['searchFilter'] ?? '',
				$filters['approvalStatus'] ?? '',
				$filters['transactionState'] ?? '',
				$filters['fromDate'] ?? null,
				$filters['toDate'] ?? null
			);
		} catch (\Exception $e) {
			$this->logger->logError(1, "An error occurred in getData: " . $e->getMessage());
			throw $e;
		}
	}

	private function getOrderByIncrementId($incrementId)
	{
		$criteria = $this->searchCriteriaBuilder
			->addFilter('increment_id', $incrementId, 'eq')
			->create();

		$orders = $this->orderRepository->getList($criteria)->getItems();

		if (empty($orders)) {
			$this->logger->logError(1, "Order not found for increment ID: " . $incrementId);
			return null;
		}

		return reset($orders); // Assuming you always want the first match
	}

	private function prepareRows(array $transactions): array
	{
		$rows = [];
		if (!empty($transactions)) {
			$rows[] = ['Date/Time (UTC)', 'Transaction ID', 'Transaction State', 'Approval Status', 'Total Amount', 'IP Address'];
			foreach ($transactions as $transaction) {
				$rows[] = [
					$transaction['date_time'] ?? '',
					$transaction['transaction_id'] ?? '',
					$transaction['transaction_state'] ?? '',
					$transaction['approval_status'] ?? '',
					$transaction['total_amount'] ?? '',
					$transaction['remote_ip'] ?? ''
				];
			}
		} else {
			$rows[] = ['No data available'];
		}
		return $rows;
	}
}
