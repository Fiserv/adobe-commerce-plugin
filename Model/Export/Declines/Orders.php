<?php 

namespace Fiserv\Payments\Model\Export\Declines;

use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Fiserv\Payments\Helper\DeclinedOrdersHelper;
		
class Orders
{
	protected $csv;
	protected $filesystem;
	protected $helper;

	public function __construct(
		Csv $csv,
		Filesystem $filesystem,
		DeclinedOrdersHelper $helper
	) {
		$this->csv = $csv;
		$this->filesystem = $filesystem;
		$this->helper = $helper;
	}

	public function getCsvFile($fileName)
	{

		$varDir = $this->filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
		$filePath = 'export/' . $fileName;
		$this->csv->saveData($varDir->getAbsolutePath($filePath), $this->getRows());

		return $filePath;
	}

	private function getRows()
	{
		$rows = [];

		$rows[] = ['Date/Time', 'Order ID', 'Customer Name', 'Order State', 'Order Amount', 'Cause of Failure', 'IP Address'];
		foreach($this->helper->getOrdersWithDeclines() as $order)
		{
			$rows[] = [
				$order['date_time'],
				$order['order_increment_id'],
				$order['customer_name'],
				$order['order_state'],
				$order['grandTotal'],
				$order['approval_status'],
				$order['remote_ip']
			];
		};

		return $rows;
	}
}
