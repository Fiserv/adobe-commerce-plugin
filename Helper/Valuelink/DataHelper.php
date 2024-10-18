<?php

namespace Fiserv\Payments\Helper\Valuelink;

use Fiserv\Payments\Model\Valuelink\ValuelinkQuoteRecord;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Serialize\Serializer\Json;


class DataHelper extends AbstractHelper
{
	private $serializer;

	public function __construct(
			Context $context, 
			Json $serializer)
	{
			parent::__construct($context);
			$this->serializer = $serializer;
	}

	public function addValuelinkRecordsToQuote(\Magento\Quote\Model\Quote $quote, array $records)
	{
		$serializedRecords = $this->serializer->serialize($records);
		$quote->setValuelinkCards($serializedRecords);
	}

	public function getValuelinkRecordsFromQuote(\Magento\Quote\Model\Quote $quote)
	{
		$serializedExistingRecords = $quote->getValuelinkCards() ?? "[]";
		$valuelinkRecords = $this->serializer->unserialize($serializedExistingRecords);

        if (!$valuelinkRecords)
		{   
			$valuelinkRecords = []; 
		}

		return $valuelinkRecords;
	}

	public function addValuelinkRecordsToTotal(\Magento\Quote\Model\Quote\Address\Total $total, array $records)
	{
		$serializedRecords = $this->serializer->serialize($records);
		$total->setValuelinkCards($serializedRecords);
	}

	public function getValuelinkRecordsFromTotal(\Magento\Quote\Model\Quote\Address\Total $total)
	{
		$serializedExistingRecords = $total->getValuelinkCards() ?? "[]";
		$valuelinkRecords = $this->serializer->unserialize($serializedExistingRecords);

        if (!$valuelinkRecords)
		{   
			$valuelinkRecords = []; 
		}

		return $valuelinkRecords;
	}

	public function isValuelinkRecordValid(int $record)
	{
		return $record > strtotime("-30 minutes");
	}
}
