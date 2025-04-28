<?php

namespace Fiserv\Payments\Model\Service\CommerceHub;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Api\CartRepositoryInterface;
use Fiserv\Payments\Api\FailedOrder\FailedOrderRepositoryInterface;
use Fiserv\Payments\Model\FailedOrderFactory;
use Fiserv\Payments\Model\ResourceModel\FailedOrder;
use Fiserv\Payments\Model\FailedOrder as FailedOrderObj;

class FailedOrderManager
{
	/**
	 *
	 * @var Json
	 */
	private $serializer;

	private $failedOrderRepo;

	private $customerRepo;

	private $failedOrderFactory;

	//private $resourceConnection;

	private $quoteRepo;

	public function __construct(
		Json $serializer,
		//ResourceConnection $resourceConnection,
		CartRepositoryInterface $quoteRepo,
		FailedOrderRepositoryInterface $failedOrderRepo,
		CustomerRepositoryInterface $customerRepo,
		FailedOrderFactory $failedOrderFactory
	) {
		$this->serializer = $serializer;
		//$this->resourceConnection = $resourceConnection;
		$this->quoteRepo = $quoteRepo;
		$this->failedOrderRepo = $failedOrderRepo;
		$this->customerRepo = $customerRepo;
		$this->failedOrderFactory = $failedOrderFactory;
	}

	public function createFailedOrder($order)
	{
		$failedOrder = $this->failedOrderFactory->create();

		$failedOrder->setOrderIncrementId($order->getData("increment_id"));
		$failedOrder->setDateTime(date("Y-m-d H:i:s"));
		$failedOrder->setOrderState($order->getData("status") ?? "FAILED");
		$failedOrder->setBillingAddress($this->getAddressByType($order->getAddresses(), "billing"));
		$failedOrder->setShippingAddress($this->getAddressByType($order->getAddresses(), "shipping"));
		$name = !is_null($order->getData("customer_firstname")) && !is_null($order->getData("customer_lastname")) ? $order->getData("customer_firstname") . " " . $order->getData("customer_lastname") : "N/A";
		$failedOrder->setCustomerName($name);
		$failedOrder->setCustomerEmail($order->getData("customer_email"));
		$failedOrder->setSubtotal($order->getData("subtotal"));
		$failedOrder->setShipping($order->getData("shipping_amount"));
		$failedOrder->setGrandTotal($order->getData("grand_total"));
		$failedOrder->setNotes($order->getData("customer_note"));
		$failedOrder->setStoreCredit($order->getData("customer_balance_amount"));
		$failedOrder->setDiscountCode($order->getData("base_discount_amount"));

		$items = array();
		foreach($order->getData("items") as $item)
		{
			$items[] = $this->arrayifyItem($item);
		}

		$failedOrder->setItems(json_encode($items));

		return $failedOrder;
	}

	public function saveFailedOrder(FailedOrderObj $failedOrder, $quoteId, $storeId)
	{
		$this->failedOrderRepo->save($failedOrder);
		// Commenting this out because the bug where the order increment ID didn't increment just "disappeared" 
		// and a new bug where the increment ID incremented TWICE showed up
		// but I don't trust that it's resolved, so I'm leaving this in here in case further debugging is necessary
		//$this->reserveIncrementId($storeId);
		$quote = $this->quoteRepo->get($quoteId);
		$quote->setReservedOrderId(null);
		$this->quoteRepo->save($quote);
	}

	public function getAddressByType(Array $addresses, string $addrType)
	{
		foreach($addresses as $addr)
		{
			if ($addr->getAddressType() === $addrType)
			{
				return $this->stringifyAddress($addr);
			}
		}

		return null;
	}

	public function stringifyAddress($address)
	{   
		return implode(" ", $address->getStreet()) . " " .
			$address->getCity() . ", " .
			$address->getRegion() . " " . 
			$address->getCountryId() . " " .
			$address->getPostcode();
	}

	public function arrayifyItem($item)
	{
		$i = array();
		$i[FailedOrderObj::KEY_ITEM_ID] = $item->getProductId();
		$i[FailedOrderObj::KEY_ITEM_NAME] = $item->getName();
		$i[FailedOrderObj::KEY_ITEM_QTY] = $item->getQtyOrdered();
		$i[FailedOrderObj::KEY_ITEM_PRICE] = $item->getPrice();

		return $i;
	}

	/*public function reserveIncrementId($storeId)
	{
		$connection = $this->resourceConnection->getConnection();
		$sequenceTable = $connection->getTableName("sequence_order_{$storeId}");
		$connection->insert($sequenceTable, ['sequence_value' => null]);
	}*/
}
