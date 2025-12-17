<?php
namespace Fiserv\Payments\Model\ResourceModel\Subscription\Order;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
	protected function _construct()
	{
		$this->_init(
			\Fiserv\Payments\Model\Subscription\Order::class,
			\Fiserv\Payments\Model\ResourceModel\Subscription\Order::class
		);
	}

	public function getActiveSubscriptionsDueForBilling()
	{
		$nowUtc = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

		$this->addFieldToFilter('status', \Fiserv\Payments\Model\Subscription\Order::STATUS_ACTIVE)
			->addFieldToFilter('sequence', 'FIRST')
			->addFieldToFilter('is_active', 1)
			->addFieldToFilter('next_billing_datetime', ['lteq' => $nowUtc])
			->setOrder('next_billing_datetime', 'ASC');

		return $this;
	}
}
