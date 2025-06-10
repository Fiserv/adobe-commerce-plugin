<?php
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Lib\CommerceHub\Model\BillingAddress;
use Fiserv\Payments\Lib\CommerceHub\Model\Address;
use Fiserv\Payments\Lib\CommerceHub\Model\Phone;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

class BillingAddressDataBuilder implements BuilderInterface
{
	const BILLING_ADDRESS_KEY = 'billingAddress';

	private $subjectReader;
	private $logger;

	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
	}

	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$orderDO = $paymentDO->getOrder();
		$billing = $orderDO->getBillingAddress();

		$address =new Address([
			'street' => implode(' ', $billing->getStreet()),
			'house_number_or_name' => $billing->getStreetLine1(),
			'recipient_name_or_address' => $billing->getFirstname() . ' ' . $billing->getLastname(),
			'city' => $billing->getCity(),
			'state_or_province' => $billing->getRegionCode(),
			'postal_code' => $billing->getPostcode(),
			'country' => $billing->getCountryId()
		]);

		$phone = new Phone([
			'phone_number' => $billing->getTelephone()
		]);

		$billingAddress = new BillingAddress([
			'first_name' => $billing->getFirstname(),
			'middle_name' => $billing->getMiddlename(),
			'last_name' => $billing->getLastname(),
			'address' => $address,
			'phone' => $phone
		]);

		$orderIncrementId = $orderDO->getOrderIncrementId();
		$this->logger->logDebug(3, "Billing Address Data Builder:\n" . $billingAddress->__toString(), "Order ID: $orderIncrementId");

		return [ self::BILLING_ADDRESS_KEY => $billingAddress ];
	}
}
