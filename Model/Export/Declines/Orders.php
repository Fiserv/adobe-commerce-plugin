<?php

namespace Fiserv\Payments\Model\Export\Declines;

use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Fiserv\Payments\Helper\DeclinedOrdersHelper;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\App\Filesystem\DirectoryList;

class Orders
{
	protected $csv;
	protected $filesystem;
	protected $declinedOrdersHelper;
	protected $logger;
	protected $varDirectory;

	public function __construct(
		Csv $csv,
		Filesystem $filesystem,
		DeclinedOrdersHelper $declinedOrdersHelper,
		MultiLevelLogger $logger
	) {
		$this->csv = $csv;
		$this->filesystem = $filesystem;
		$this->declinedOrdersHelper = $declinedOrdersHelper;
		$this->logger = $logger;
		$this->varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
	}

	public function getCsvFile($fileName, array $filters = [], $type = 'orders')
	{
		$data = $this->getData($filters, $type);

		$rows = $this->prepareRows($data['orders']);
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

	public function getExcelFile($fileName, array $filters = [], $type = 'orders')
	{
		$data = $this->getData($filters, $type);

		$rows = $this->prepareRows($data['orders']);
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
		return $this->declinedOrdersHelper->getOrdersWithDeclines(
			1,
			10000,
			$filters['searchFilter'] ?? '',
			$filters['approvalStatus'] ?? '',
			$filters['orderState'] ?? '',
			$filters['fromDate'] ?? null,
			$filters['toDate'] ?? null,
			$type // pass the type to helper
		);
	}

	private function prepareRows(array $orders): array
	{
		$rows = [];
		if (!empty($orders)) {
			$rows[] = ['Date/Time (UTC)', 'Order ID', 'Customer Name', 'Order State', 'Order Amount', 'Cause of Failure', 'IP Address'];
			foreach ($orders as $order) {
				$rows[] = [
					$order['date_time'] ?? '',
					$order['order_increment_id'] ?? '',
					$order['customer_name'] ?? '',
					$order['order_state'] ?? '',
					$order['grandTotal'] ?? '',
					$order['approval_status'] ?? '',
					$order['remote_ip'] ?? ''
				];
			}
		} else {
			$rows[] = ['No data available'];
		}
		return $rows;
	}
}
